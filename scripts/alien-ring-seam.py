"""Offline registered head-only seam; never used to deform runtime limbs."""
from pathlib import Path
import cv2
import numpy as np
from PIL import Image

ROOT=Path(__file__).resolve().parents[1]
OUT=ROOT/'assets/alien'
def frame(name,n):
    im=Image.open(OUT/name).convert('RGBA')
    return np.array(im.crop(((n%10)*300,(n//10)*400,(n%10+1)*300,(n//10)*400+240)))

def seam(steps=17):
    a,b=frame('gaze-lower-raw.webp',72),frame('gaze-circle-raw.webp',0)
    pairs=[
      ((0,0),(0,0)),((299,0),(299,0)),((299,239),(299,239)),((0,239),(0,239)),
      ((142,96),(153,94)),((107,103),(103,100)),((80,124),(77,119)),
      ((63,151),(57,146)),((63,182),(58,182)),((81,201),(77,201)),
      ((125,218),(126,225)),((172,224),(174,227)),((210,214),(218,214)),
      ((221,195),(234,194)),((223,164),(232,159)),((213,128),(214,122)),((191,106),(185,104)),
      ((123,164),(92,165)),((135,143),(102,144)),((156,139),(121,137)),
      ((171,147),(137,144)),((177,164),(146,163)),((170,180),(139,179)),
      ((150,186),(121,185)),((133,180),(103,178)),
      ((150,151),(120,151)),((164,148),(132,149)),((175,159),(144,160)),
      ((174,175),(143,174)),((160,179),(132,180)),((150,169),(121,170)),
      ((205,168),(177,176)),((207,150),(181,157)),((214,142),(197,150)),
      ((219,151),(216,154)),((223,169),(226,174)),((218,183),(219,190)),
      ((210,186),(201,198)),((206,177),(183,193)),
      ((210,153),(202,161)),((217,152),(212,158)),((221,164),(221,175)),
      ((217,179),(213,191)),((211,179),(202,190)),((208,166),(201,176)),
      ((183,186),(162,191)),((183,199),(138,203)),((213,202),(173,210)),((199,205),(157,212)),
      ((140,125),(112,123)),((160,125),(136,126)),((210,129),(194,137)),((218,132),(217,145)),
      ((126,102),(124,99)),((115,74),(112,73)),((106,62),(100,54)),
      ((182,100),(198,108)),((190,79),(222,90)),((199,67),(238,78)),
    ]
    for ac,bc,ar,br in [((103,48),(98,38),14,17),((204,56),(245,74),14,18)]:
        for theta in np.linspace(0,2*np.pi,8,endpoint=False):
            pairs.append(((ac[0]+ar*np.cos(theta),ac[1]+ar*np.sin(theta)),(bc[0]+br*np.cos(theta),bc[1]+br*np.sin(theta))))
    pa=np.float32([p[0] for p in pairs]);pb=np.float32([p[1] for p in pairs]);middle=(pa+pb)/2
    subdiv=cv2.Subdiv2D((0,0,300,240))
    for p in middle:subdiv.insert(tuple(map(float,p)))
    triangles=[]
    for tri in subdiv.getTriangleList().reshape(-1,3,2):
        ids=[int(np.argmin(np.sum((middle-p)**2,axis=1))) for p in tri]
        if max(np.linalg.norm(middle[i]-p) for i,p in zip(ids,tri))<.1:triangles.append(ids)
    def premul(im):
        p=im.astype(np.float32)/255;p[:,:,:3]*=p[:,:,3:4];return p
    a0,b0=premul(a),premul(b)
    result=[]
    for t in np.linspace(0,1,steps):
        texture_mix=max(0,(t-.8)/.2)
        target=np.float32(pa*(1-t)+pb*t);value=np.zeros((240,300,4),np.float32);weight=np.zeros((240,300,1),np.float32)
        alphaA=np.zeros((240,300),np.float32);alphaB=alphaA.copy()
        for ids in triangles:
            dst=target[ids];mask=np.zeros((240,300),np.uint8);cv2.fillConvexPoly(mask,np.int32(np.round(dst)),255,lineType=cv2.LINE_AA)
            m=mask.astype(np.float32)[:,:,None]/255
            wa=cv2.warpAffine(a0,cv2.getAffineTransform(pa[ids],dst),(300,240),flags=cv2.INTER_LINEAR)
            wb=cv2.warpAffine(b0,cv2.getAffineTransform(pb[ids],dst),(300,240),flags=cv2.INTER_LINEAR)
            value+=(wa*(1-texture_mix)+wb*texture_mix)*m;weight+=m
            alphaA+=wa[:,:,3]*m[:,:,0];alphaB+=wb[:,:,3]*m[:,:,0]
        value/=np.maximum(weight,.001);value[:,:,:3]/=np.maximum(value[:,:,3:4],.001)
        def signed(alpha):
            mask=np.uint8(alpha/np.maximum(weight[:,:,0],.001)>.2)
            return cv2.distanceTransform(mask,cv2.DIST_L2,3)-cv2.distanceTransform(1-mask,cv2.DIST_L2,3)
        value[:,:,3]=np.clip(.5+signed(alphaA)*(1-t)+signed(alphaB)*t,0,1)
        result.append(np.uint8(np.clip(value,0,1)*255))
    result[0],result[-1]=a,b
    return result

if __name__=='__main__':
    images=seam();s=Image.new('RGBA',(1500,240),(21,21,47,255))
    for j,i in enumerate([0,4,8,12,16]):s.alpha_composite(Image.fromarray(images[i]),(j*300,0))
    s.convert('RGB').save(OUT/'ring-seam-preview.jpg')
