# Contact alien — active Kling version (2026-11-02)

The user rejected the layered puppet because the arm visibly deformed. home.php
now loads manifest.js, motion.js and contact-alien.js. Puppet/math scripts and
clean plate remain only as rejected local experiments and are NOT requested by
the page. No split-arm mesh or synthetic pupil layers are active.

## New native clips

- wave-camera.webp: new Kling3.0Turbo greeting. The character turns toward the
  camera, waves its existing hand naturally, then returns to its original pose.
  The source ended in a frontal resting pose; a short inspected resting-pose
  bridge joins the matching intro frame16, followed by the reversed intro.
- reach-natural.webp: new Kling3.0Turbo close reach, intact head/body/arm frames.
  Native frames0–44 are followed by43–0. This preserves anatomy and gives exact
  matching return poses without optical-flow interpolation of an arm.
- Generated detached insect was removed by chroma-alpha component processing;
  its original positions supply the invisible pose-selection guide.
- Interactive hold uses complete frames35–44 selected nearest the close cursor
  for up to1100ms. The available target path is approximately32×23atlas pixels;
  this is limited recorded-pose following, not unrestricted2D/3D hand control.
- Leaving early switches to returnMirror88−currentFrame. Both frame indices
  represent the same source pose, so the arm retracts without extending first.

## Behaviour and loading

Gaze uses previously inspected open-eye head paths. Response easing was sped up.
Each direction still joins through neutral. Existing blink, reduced-motion,
visibility pausing, loading fallback and lazy gesture decoding are retained.

Reach triggers only within34atlas pixels of the recorded target path, after450ms
near dwell, with55% probability once per approach and a9–16second cooldown.
It does not trigger for distant cursor movement. Wave happens periodically.

Both new source clips were generated in the signed-in Kling account for24free
credits each. The last visible balance was18. No paid package was purchased.
The Kling project is946166292376715310. SourceMP4s are in the normal download
folder under their UUIDs3cb48b20... (wave) ande150d47e... (reach).
Service watermarks are preserved. No production deployment was performed.

## Validation

- node --check contact-alien.js
- node --check assets/alien/manifest.js
- node --check assets/alien/motion.js
- node --test tests/contact-alien-frames.test.cjs (10passed)
- php -l home.php

Read-only frontend review used the repository profile through an ordinary
subagent. Its early-cancellation finding was fixed using exact mirrored return
poses and tested. Desktop browser verified coherent reaching and camera-facing
wave; no browser errors were recorded. Screenshots are in the task visualization
folder. The rejected arm-return optical-flow sample is only in OS temp and was
not used for the active reach.
