"""Build open-eye gaze poses and short, aligned bridges from approved local clips.

Only small pose differences are interpolated. Arm gestures remain original video
frames. Run after alien-video-atlas.py; no network or external API access.
"""
import json
import hashlib
import math
from pathlib import Path
import cv2
import numpy as np
from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'assets/alien'

def frame(name, n):
    im = Image.open(OUT / name).convert('RGBA')
    return np.array(im.crop(((n % 10)*300, (n//10)*400, (n%10+1)*300, (n//10+1)*400)))

def premultiply(im):
    a = im.astype(np.float32)/255
    a[:,:,:3] *= a[:,:,3:4]
    return a

def unpack(a):
    a = a.copy()
    a[:,:,:3] /= np.maximum(a[:,:,3:4], .001)
    return np.uint8(np.clip(a, 0, 1)*255)

def flow(a, b):
    def gray(im):
        p = premultiply(im)
        rgb = p[:,:,:3] + (1-p[:,:,3:4])*np.array([.08,.08,.17])
        return cv2.cvtColor(np.uint8(rgb*255), cv2.COLOR_RGB2GRAY)
    solver=cv2.DISOpticalFlow_create(cv2.DISOPTICAL_FLOW_PRESET_MEDIUM)
    return solver.calc(gray(a),gray(b),None)

def morph(a, b, steps=12):
    f, g = flow(a,b), flow(b,a)
    yy,xx = np.mgrid[:a.shape[0],:a.shape[1]].astype(np.float32)
    pa,pb = premultiply(a),premultiply(b)
    result=[]
    for i in range(steps):
        t=i/(steps-1)
        # Eased velocity is zero at each boundary; warp before blending to avoid
        # two displaced silhouettes at the entrance/exit of a gesture.
        t=t*t*(3-2*t)
        wa=cv2.remap(pa,xx-f[:,:,0]*t,yy-f[:,:,1]*t,cv2.INTER_LINEAR)
        wb=cv2.remap(pb,xx-g[:,:,0]*(1-t),yy-g[:,:,1]*(1-t),cv2.INTER_LINEAR)
        result.append(unpack(wa*(1-t)+wb*t))
    result[0],result[-1]=a,b
    return result

def atlas(name, frames, height=400, columns=10):
    sheet=Image.new('RGBA',(columns*300,math.ceil(len(frames)/columns)*height))
    for i,im in enumerate(frames): sheet.paste(Image.fromarray(im),((i%columns)*300,(i//columns)*height))
    sheet.save(OUT/name,quality=88,method=2)
    revision=hashlib.sha256((OUT/name).read_bytes()).hexdigest()[:12]
    return dict(src=name+'?v='+revision,width=300,height=height,columns=columns,frames=len(frames),fps=30)

base=np.array(Image.open(OUT/'wave-poster.png').convert('RGBA'))

def head_path(frames):
    return [im[:240] for im in frames]

# Blink frames are excluded from gaze. The small vertical gap is bridged
# between two inspected, open-eye frames; gestures keep their native anatomy.
up=[base]+morph(base,frame('pitch-raw.webp',7),8)[1:]+morph(frame('pitch-raw.webp',7),frame('pitch-raw.webp',21),14)[1:]+[frame('pitch-raw.webp',i) for i in range(22,29)]
down=[base]+morph(base,frame('pitch-raw.webp',56),8)[1:]+[frame('pitch-raw.webp',i) for i in range(57,73)]
# Yaw paths are selected from the new short continuous recording after review.
yaw=json.loads((OUT/'yaw-selection.json').read_text())
paths={'up':up,'down':down}
for direction in ['left','right']:
    spec=yaw[direction]
    images=[frame('yaw-new.webp',i) for i in spec]
    paths[direction]=[base]+morph(base,images[0],8)[1:]+images[1:]
poses=[]; directions={}
for direction,images in paths.items():
    directions[direction]={'start':len(poses),'frames':len(images)}
    poses+=head_path(images)
manifest={'gaze':dict(atlas('gaze-paths.webp',poses,240),directions=directions),'bridges':{}}
for name in ['blink','wave','reach']:
    src='blink-short.webp' if name=='blink' else name+'.webp'
    count=32 if name=='blink' else 73
    first,last=frame(src,0),frame(src,count-1)
    if name=='blink':
        first[240:]=base[240:];last[240:]=base[240:]
    manifest['bridges'][name]=atlas(name+'-bridge.webp',morph(base,first)+morph(last,base))
(OUT/'motion.json').write_text(json.dumps(manifest,indent=2))
(OUT/'motion.js').write_text('window.VegaAlienMotion = '+json.dumps(manifest)+';\n')
print(json.dumps(manifest))
