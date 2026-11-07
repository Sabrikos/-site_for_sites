"""Build small rest-pose transitions for the tariffs mascot's native video frames."""
from pathlib import Path
import json, math, hashlib
import cv2
import numpy as np
from PIL import Image
ROOT=Path(__file__).resolve().parents[1]
OUT=ROOT/'assets/alien/tariffs'
# Reuse only the generic optical-flow helpers, never the archived tracking build.
helpers=(ROOT/'scripts/alien-motion-build.py').read_text(encoding='utf-8')
exec(helpers[helpers.index('def premultiply'):helpers.index('def atlas')])
W,H=320,326

def frame(sheet,n):
 return np.array(sheet.crop(((n%10)*W,(n//10)*H,(n%10+1)*W,(n//10+1)*H)))
def atlas(name,frames):
 sheet=Image.new('RGBA',(W*10,H*math.ceil(len(frames)/10)))
 for n,f in enumerate(frames): sheet.paste(Image.fromarray(f),((n%10)*W,(n//10)*H))
 sheet.save(OUT/name,quality=88,method=2)
 return spec(name,len(frames),30)
def spec(name,count,fps):
 revision=hashlib.sha256((OUT/name).read_bytes()).hexdigest()[:12]
 return dict(src='tariffs/'+name+'?v='+revision,width=W,height=H,columns=10,frames=count,fps=fps)
base=np.array(Image.open(OUT/'blink-poster.png').convert('RGBA'))
Image.fromarray(base).save(OUT/'idle-poster.png')
manifest=dict(width=W,height=H,freezeBodyY=None,mark=[250,305,70,21],poster='tariffs/idle-poster.png')
for name in ['blink','wave']:
 meta=json.loads((OUT/(name+'.json')).read_text())
 sheet=Image.open(OUT/(name+'.webp')).convert('RGBA')
 first,last=frame(sheet,0),frame(sheet,meta['frames']-1)
 clip=spec(name+'.webp',meta['frames'],meta['fps'])
 clip['bridge']=atlas(name+'-bridge.webp',morph(base,first)+morph(last,base))
 manifest[name]=clip
(OUT/'idle-manifest.js').write_text('window.VegaAlienIdle = Object.freeze('+json.dumps(manifest,ensure_ascii=False)+');\n',encoding='utf-8')
print(json.dumps(manifest))
