"""Convert an authorized green-screen mascot video into a browser frame atlas.

Usage: python scripts/alien-video-atlas.py INPUT.mp4 OUTPUT.webp
Requires existing Pillow, OpenCV and NumPy. Keeps any service watermark.
"""
import argparse
import json
import math
from pathlib import Path

import cv2
import numpy as np
from PIL import Image

parser = argparse.ArgumentParser()
parser.add_argument('source', type=Path)
parser.add_argument('output', type=Path)
parser.add_argument('--start', type=float, default=0)
parser.add_argument('--end', type=float, default=10)
parser.add_argument('--method', type=int, choices=range(7), default=6)
parser.add_argument('--clean-props', action='store_true')
parser.add_argument('--width', type=int, default=300)
parser.add_argument('--height', type=int, default=400)
args = parser.parse_args()
if not (16 <= args.width <= 2048 and 16 <= args.height <= 2048):
    raise SystemExit('Expected output dimensions between 16 and 2048')
w, h = args.width, args.height
capture = cv2.VideoCapture(str(args.source))
if not capture.isOpened():
    raise SystemExit('Cannot open source video')
rate = capture.get(cv2.CAP_PROP_FPS)
if not math.isfinite(rate) or rate <= 0 or not (0 <= args.start < args.end <= 10):
    raise SystemExit('Expected valid FPS and a time interval within 10 seconds')
output_rate = min(30, rate)
frames = []
targets = []
next_time = args.start
index = 0
while True:
    ok, bgr = capture.read()
    if not ok:
        break
    time = index / rate
    index += 1
    if time > args.end:
        break
    if time + 0.0001 < next_time:
        continue
    next_time += 1 / output_rate
    rgb = cv2.cvtColor(cv2.resize(bgr, (args.width, args.height), interpolation=cv2.INTER_AREA), cv2.COLOR_BGR2RGB)
    color = rgb.astype(np.float32)
    excess = color[:, :, 1] - np.maximum(color[:, :, 0], color[:, :, 2])
    alpha = np.clip((65 - excess) / 50, 0, 1)
    # Drop detached tiny generated specks; preserve the watermark at the bottom.
    count, labels, stats, _ = cv2.connectedComponentsWithStats((alpha > .5).astype(np.uint8), 8)
    for component in range(1, count):
        if args.clean_props and 5 < stats[component, cv2.CC_STAT_AREA] < 200 and stats[component, cv2.CC_STAT_LEFT] > 235 and stats[component, cv2.CC_STAT_TOP] < 355:
            targets.append([len(frames),float(stats[component,cv2.CC_STAT_LEFT]+stats[component,cv2.CC_STAT_WIDTH]/2),float(stats[component,cv2.CC_STAT_TOP]+stats[component,cv2.CC_STAT_HEIGHT]/2)])
            x,y,w,h=stats[component,:4]
            alpha[max(0,y-3):min(args.height,y+h+3),max(0,x-3):min(args.width,x+w+3)]=0
        if stats[component, cv2.CC_STAT_AREA] < 16 and stats[component, cv2.CC_STAT_TOP] < args.height * .9:
            alpha[labels == component] = 0
    # Despill only the semi-transparent boundary, preserving the purple interior.
    edge = alpha < 0.99
    color[:, :, 1][edge] = np.minimum(color[:, :, 1][edge], np.maximum(color[:, :, 0][edge], color[:, :, 2][edge]))
    rgba = np.dstack((color.astype(np.uint8), (alpha * 255).astype(np.uint8)))
    frames.append(Image.fromarray(rgba))
capture.release()
if not frames:
    raise SystemExit('No frames decoded')
columns = 10
atlas = Image.new('RGBA', (args.width * columns, args.height * math.ceil(len(frames) / columns)))
for n, frame in enumerate(frames):
    atlas.paste(frame, ((n % columns) * args.width, (n // columns) * args.height))
args.output.parent.mkdir(parents=True, exist_ok=True)
atlas.save(args.output, format='WEBP', lossless=False, quality=85, method=args.method)
metadata = dict(src=args.output.name, width=args.width, height=args.height, columns=columns, frames=len(frames), fps=output_rate)
args.output.with_suffix('.json').write_text(json.dumps(metadata, indent=2), encoding='utf-8')
frames[0].save(args.output.with_name(args.output.stem + '-poster.png'))
if args.clean_props:
    args.output.with_name(args.output.stem+'-targets.json').write_text(json.dumps(targets),encoding='utf-8')
print(json.dumps(metadata))
