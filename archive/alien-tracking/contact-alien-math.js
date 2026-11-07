/* Geometry is shared by the renderer and its deterministic tests. */
(function(scope){
  'use strict';
  const clamp=(v,a,b)=>Math.max(a,Math.min(b,v));
  const shoulder={x:432,y:871}, elbow={x:381,y:997}, wrist={x:350,y:1095};
  const l1=Math.hypot(elbow.x-shoulder.x,elbow.y-shoulder.y);
  const l2=Math.hypot(wrist.x-elbow.x,wrist.y-elbow.y);
  function solve(x,y){
    let dx=x-shoulder.x,dy=y-shoulder.y;
    const raw=Math.hypot(dx,dy);
    if(raw<.001){dx=-1;dy=1;}
    const angle=Math.atan2(dy,dx),d=clamp(raw,Math.abs(l1-l2)+.1,l1+l2-.1);
    const offset=Math.acos(clamp((l1*l1+d*d-l2*l2)/(2*l1*d),-1,1));
    const a=angle+offset;
    const e={x:shoulder.x+Math.cos(a)*l1,y:shoulder.y+Math.sin(a)*l1};
    const w={x:shoulder.x+Math.cos(angle)*d,y:shoulder.y+Math.sin(angle)*d};
    return {elbow:e,wrist:w,a,b:Math.atan2(w.y-e.y,w.x-e.x)};
  }
  function damp(value,velocity,target,dt,omega=22){
    const offset=value-target,decay=Math.exp(-omega*dt),c=velocity+omega*offset;
    return [target+(offset+c*dt)*decay,(velocity-omega*c*dt)*decay];
  }
  const api={clamp,shoulder,elbow,wrist,l1,l2,solve,damp};
  if(typeof module==='object'&&module.exports)module.exports=api;
  else scope.VegaAlienMath=api;
})(typeof window==='object'?window:this);
