1. Text to image
```
curl -f -sS "https://api.stability.ai/v2beta/stable-image/generate/ultra" \
  -H "authorization: Bearer sk-MYAPIKEY" \
  -H "accept: image/*" \
  -F prompt="Lighthouse on a cliff overlooking the ocean" \
  -F output_format="webp" \
  -o "./lighthouse.webp"
```

2.  sketch to proper image
```
curl -f -sS "https://api.stability.ai/v2beta/stable-image/control/sketch" \
  -H "authorization: Bearer sk-MYAPIKEY" \
  -H "accept: image/*" \
  -F image=@"./sketch.png" \
  -F prompt="a medieval castle on a hill" \
  -F control_strength=0.7 \
  -F output_format="webp" \
  -o "./castle.webp"
```

PROMPT: based on this rough sketch, create me an an architectural design  that has colou all green with nordic style
PROMPT: Based on this rough sketch, created me an an architectural design  that is all green with Nordic style, this is a living room.
PROMPT: Based on this rough sketch, help me create an architectural design that is all green and Nordic in style. This is a living room.

Describe your vision! For example: 'Create a modern living room with warm lighting, cozy furniture, and a large window overlooking the city skyline.' Or, 'Transform my rough sketch into a realistic 3D render of a beachfront villa with palm trees and a sunset view

for each Image render it spent 6-9 Stability API Credits
price per credits = $10/ 1000 = $0,06 - $0,09