import cv2,numpy as np
from PIL import Image
from pathlib import Path
out=Path('assets/alien')
for folder,w,h in [('gaze-new',300,400),('tariffs/gaze-new',320,326)]:
 root=out/folder; ims={n:np.array(Image.open(root/(n+'.webp')).convert('RGBA')) for n in ['center','left','right','up','down']}; base=ims['center'];
 def gray(a):
  rgba=a.astype(np.float32)/255; bg=np.array([.09,.08,.20],dtype=np.float32);return cv2.cvtColor(np.uint8((rgba[:,:,:3]*rgba[:,:,3:4]+bg*(1-rgba[:,:,3:4]))*255),cv2.COLOR_RGB2GRAY)
 flows={};solver=cv2.DISOpticalFlow_create(cv2.DISOPTICAL_FLOW_PRESET_MEDIUM)
 for n in ['left','right','up','down']: flows[n]=solver.calc(gray(base),gray(ims[n]),None)
 yy,xx=np.mgrid[:h,:w].astype(np.float32);sheet=Image.new('RGBA',(w*9,h*9));p=base.astype(np.float32)/255;p[:,:,:3]*=p[:,:,3:4]
 for row in range(9):
  y=(row-4)/4
  for col in range(9):
   x=(col-4)/4; fx=flows['right' if x>0 else 'left']*abs(x);fy=flows['down' if y>0 else 'up']*abs(y); f=fx+fy
   a=cv2.remap(p,xx-f[:,:,0],yy-f[:,:,1],cv2.INTER_LINEAR,borderMode=cv2.BORDER_CONSTANT);a[:,:,:3]/=np.maximum(a[:,:,3:4],.001);a=np.uint8(np.clip(a*255,0,255));sheet.paste(Image.fromarray(a),(col*w,row*h))
 sheet.save(root/'continuous.webp',quality=87,method=2)
 print(folder,(root/'continuous.webp').stat().st_size)
