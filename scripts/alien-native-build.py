"""Prepare whole native video frames, measured gaze and idle clips."""
from pathlib import Path
import json
import cv2
import numpy as np
from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'assets/alien/cursor-video/library'
OUT.mkdir(parents=True, exist_ok=True)
SOURCES = [
    ('gaze', '_tenant2Fda5bdd3e-24c8-4ca9-9351-674451c5b77c.mp4', 'tracking'),
    ('rest', '_tenant2F5a301a75-1e46-4057-887a-5726864d0c84.mp4', 'idle'),
    ('legs', '_tenant2Fd7d54ca6-d9df-4058-9be5-59a9ab22358c.mp4', 'idle'),
    ('turn', '_tenant2F469e9966-420b-4503-8423-2c0df061d91a.mp4', 'tracking'),
    ('wide', 'tenant2F05c17d78-94c5-4348-b0e4-f34d742b52a2.mp4', 'tracking'),
    ('original', '_tenant2F9d283d91-44bf-409a-97a9-9fd6999a8e00.mp4', 'tracking'),
]
clips = []
for name, filename, role in SOURCES:
    capture = cv2.VideoCapture(str(ROOT.parent / filename))
    if not capture.isOpened():
        raise SystemExit('Missing source ' + filename)
    fps = capture.get(cv2.CAP_PROP_FPS)
    raw, masks, bounds = [], [], []
    while True:
        ok, bgr = capture.read()
        if not ok:
            break
        rgb = cv2.cvtColor(bgr, cv2.COLOR_BGR2RGB).astype(np.float32)
        if name == 'original':
            alpha = np.clip((rgb.max(axis=2)-10)/35,0,1)
        else:
            excess = rgb[:,:,1]-np.maximum(rgb[:,:,0],rgb[:,:,2])
            alpha = np.clip((65-excess)/45,0,1)
            edge = alpha < .99
            rgb[:,:,1][edge] = np.minimum(rgb[:,:,1][edge],np.maximum(rgb[:,:,0][edge],rgb[:,:,2][edge]))
        ys,xs = np.where(alpha>.6)
        bounds.append((xs.min(),ys.min(),xs.max()+1,ys.max()+1))
        raw.append(rgb.astype(np.uint8));masks.append(np.uint8(alpha*255))
    capture.release()
    # One transform for the entire clip prevents per-frame scaling and bobbing.
    boxes=np.array(bounds)
    left,top=boxes[:,:2].min(axis=0)
    right,bottom=boxes[:,2:].max(axis=0)
    factor=min(250/(right-left),340/(bottom-top))
    neutral=boxes[0]
    cx=(neutral[0]+neutral[2])/2
    matrix=np.float32([[factor,0,150-cx*factor],[0,factor,30-top*factor]])
    frames=[];measurements=[]
    first_hsv=cv2.cvtColor(raw[0],cv2.COLOR_RGB2HSV)
    h,w=first_hsv.shape[:2]
    # Locate two neutral white eye shapes in the middle-upper character region.
    white=np.uint8((first_hsv[:,:,1]<90)&(first_hsv[:,:,2]>185))*255
    y0=int(neutral[1]+(neutral[3]-neutral[1])*.27)
    y1=int(neutral[1]+(neutral[3]-neutral[1])*.56)
    white[:y0]=0;white[y1:]=0
    contours,_=cv2.findContours(white,cv2.RETR_EXTERNAL,cv2.CHAIN_APPROX_SIMPLE)
    eyes=sorted([c for c in contours if cv2.contourArea(c)>100],key=cv2.contourArea,reverse=True)[:2]
    rois=[]
    for eye in eyes:
        x,y,ew,eh=cv2.boundingRect(eye)
        rois.append((max(0,x-int(ew*.2)),max(0,y+int(eh*.08)),min(w,x+int(ew*1.2)),min(h,y+eh)))
    for rgb,mask in zip(raw,masks):
        rgba=np.dstack((rgb,mask))
        rendered=cv2.warpAffine(rgba,matrix,(300,400),flags=cv2.INTER_AREA)
        frames.append(Image.fromarray(rendered))
        hsv=cv2.cvtColor(rgb,cv2.COLOR_RGB2HSV);points=[]
        for x0,y0,x1,y1 in rois:
            roi=hsv[y0:y1,x0:x1]
            white_count=np.count_nonzero((roi[:,:,1]<90)&(roi[:,:,2]>185))
            if white_count<(x1-x0)*(y1-y0)*.14:break
            dark=np.uint8(roi[:,:,2]<100)
            n,labels,stats,centers=cv2.connectedComponentsWithStats(dark)
            candidates=[i for i in range(1,n) if stats[i,4]>80 and stats[i,3]>(y1-y0)*.25]
            if not candidates:break
            i=max(candidates,key=lambda i:stats[i,4])
            px,py=centers[i]
            points.append([x0+px,y0+py])
        measurements.append(np.mean(points,axis=0) if len(points)==2 else [np.nan,np.nan])
    values=np.array(measurements);valid=np.isfinite(values).all(axis=1)
    gaze=[None]*len(frames)
    if valid.sum()>10:
        for axis in range(2):
            filled=np.interp(np.arange(len(values)),np.flatnonzero(valid),values[valid,axis])
            values[:,axis]=[np.median(filled[max(0,i-2):i+3]) for i in range(len(values))]
        values-=values[0]
        for axis in range(2):
            for sign in [-1,1]:
                side=values[:,axis]*sign>0
                extent=np.percentile(np.abs(values[side,axis]),98) if side.any() else 1
                values[side,axis]/=max(extent,8/factor*.45)
        values=np.clip(values,-1,1)
        gaze=[[round(float(x),4),round(float(y),4)] if valid[i] else None for i,(x,y) in enumerate(values)]
    atlas=Image.new('RGBA',(3000,400*((len(frames)+9)//10)))
    for i,frame in enumerate(frames):atlas.paste(frame,(i%10*300,i//10*400))
    atlas.save(OUT/(name+'.webp'),quality=84,method=2)
    frames[0].save(OUT/(name+'-poster.png'))
    # Low-resolution native-pose signatures permit real-pose matching across clips.
    signatures=[]
    for frame in frames:
        small=frame.resize((12,16)).convert('RGBA')
        a=np.array(small,dtype=float)
        a[:,:,:3]*=a[:,:,3:4]/255
        signatures.append(np.uint8(a[:,:,:3]).flatten().tolist())
    clips.append(dict(id=name,source=filename,role=role,src='library/'+name+'.webp',
                      count=len(frames),fps=fps,gaze=gaze,poses=signatures))
    print(name,len(frames),int(valid.sum()),flush=True)
# For every destination frame, record the most compatible outgoing native pose.
for destination in clips:
    destination['links']={}
    target_poses=np.array(destination['poses'],dtype=np.float32)
    for source in clips:
        if source is destination:continue
        source_poses=np.array(source['poses'],dtype=np.float32)
        matches=[]
        for target in target_poses:
            costs=np.mean(np.abs(source_poses-target),axis=1)/255
            frame=int(np.argmin(costs))
            matches.append([frame,round(float(costs[frame]),5)])
        destination['links'][source['id']]=matches
data=dict(width=300,height=400,columns=10,clips=clips)
(OUT.parent/'measured.js').write_text('window.VegaAlienNative = '+json.dumps(data,separators=(',',':'))+';\n',encoding='utf-8')
print('All six native clips prepared.')
