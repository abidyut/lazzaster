/* ------------- CONFIG & STATE ------------- */
const PAY_RANGE={down:1,seven:4,up:1};
const PAY_SUM={2:26,3:12,4:8,5:6,6:5,7:4,8:5,9:6,10:8,11:12,12:26};
const BET_TIME=15, PREROLL_MIN=2, PREROLL_MAX=3;
const MIN_BET=0.10, MULT_MIN=2, MULT_MAX=300;
let multCooldown = 0;

// Balance is managed by auth module and game page initialization
let betState = { range:{down:0,seven:0,up:0}, sum:Object.fromEntries(Array.from({length:11},(_,i)=>[i+2,0])) };
let lastBet=null, timer=BET_TIME, tId=null, rolling=false, tx=300000+Math.floor(Math.random()*10000), multipliers={}, phase='bet', roundNum=0, balanceReady=false;

/* helpers */
const $ = s => document.querySelector(s), $$ = s => Array.from(document.querySelectorAll(s));
const fmt = n => (Math.round(n*100)/100).toFixed(2) + ' ZST';
function rnd(arr){ return arr[Math.floor(Math.random()*arr.length)]; }
// Balance persistence handled by server API - removed localStorage save

/* audio simple */
const audioCtx = (()=>{ try{return new (window.AudioContext||window.webkitAudioContext)()}catch(e){return null} })();
const unlock = ()=>{ if(!audioCtx) return; if(audioCtx.state==='suspended') audioCtx.resume(); }
document.addEventListener('pointerdown', unlock, {once:true});
function tone(f,d,t,g=0.05){ if(!audioCtx) return; const now=audioCtx.currentTime; const o=audioCtx.createOscillator(); const gg=audioCtx.createGain(); o.type=t; o.frequency.setValueAtTime(f,now); gg.gain.setValueAtTime(g,now); o.connect(gg); gg.connect(audioCtx.destination); o.start(now); o.stop(now+d); }
function sfx(kind){ if(!audioCtx) return; if(kind==='roll'){ tone(420,0.06,'sawtooth',0.02); setTimeout(()=>tone(520,0.06,'sawtooth',0.02),80);} if(kind==='win'){ tone(880,0.12,'sine',0.09); setTimeout(()=>tone(1320,0.08,'sine',0.07),110);} if(kind==='lose'){ tone(220,0.14,'sine',0.06); setTimeout(()=>tone(170,0.08,'sine',0.05),90);} if(kind==='click'){ tone(520,0.04,'square',0.05);} if(kind==='zap'){ tone(1500,0.06,'square',0.08); setTimeout(()=>tone(300,0.08,'triangle',0.06),80); }}

/* ---------- UI build ---------- */
/* build sum grid 2..12 (always visible) */
const grid = $("#grid");
for(let s=2;s<=12;s++){
  const c=document.createElement('div');
  c.className='gcell'; c.dataset.type='sum'; c.dataset.key=String(s);
  c.innerHTML = `<div style="font-weight:800;font-size:16px">${s}</div><div class="od">1:${PAY_SUM[s]}</div><div class="chip" data-chip>0.00</div><div class="mul" data-mul>×?</div>`;
  grid.appendChild(c);
}

/* build die faces for two dice (pips positioned) */
function buildDie(el){
  const faces = {
    1:["p_c"],
    2:["p_tl","p_br"],
    3:["p_tl","p_c","p_br"],
    4:["p_tl","p_tr","p_bl","p_br"],
    5:["p_tl","p_tr","p_c","p_bl","p_br"],
    6:["p_tl","p_tr","p_ml","p_mr","p_bl","p_br"]
  };
  el.innerHTML='';
  // create 6 face containers with class names f1..f6 matching CSS transforms
  for(let i=1;i<=6;i++){
    const f=document.createElement('div'); f.className='face f'+i;
    // add pips for that face
    (faces[i]||[]).forEach(pos=>{
      const pip=document.createElement('div'); pip.className='pip '+pos; f.appendChild(pip);
    });
    el.appendChild(f);
  }
}

/* dice orientation map for final snap (these set the die transform to show face X) */
const ori = {
  1:"rotateX(0deg) rotateY(0deg)",
  2:"rotateX(0deg) rotateY(-90deg)",
  3:"rotateX(-90deg) rotateY(0deg)",
  4:"rotateX(90deg) rotateY(0deg)",
  5:"rotateX(0deg) rotateY(90deg)",
  6:"rotateX(0deg) rotateY(180deg)"
};

/* realistic dice animation: jitter then settle to orientations */
function animateDiceToFaces(d1,d2,a,b,cb){
  sfx('roll');
  const jitter = setInterval(()=>{
    d1.style.transform = `rotateX(${Math.random()*720}deg) rotateY(${Math.random()*720}deg)`;
    d2.style.transform = `rotateX(${Math.random()*720}deg) rotateY(${Math.random()*720}deg)`;
  },70);
  setTimeout(()=>{ clearInterval(jitter);
    d1.style.transition='transform .8s cubic-bezier(.2,.8,.2,1)';
    d2.style.transition='transform .8s cubic-bezier(.2,.8,.2,1)';
    d1.style.transform = ori[a];
    d2.style.transform = ori[b];
    setTimeout(()=>{ d1.style.transition=''; d2.style.transition=''; if(cb) cb(); }, 900);
  }, 1100);
}

/* UI refresh */
function refreshUI(){
  $("#balance").textContent = fmt(balance);
  const total = Object.values(betState.range).reduce((a,b)=>a+b,0) + Object.values(betState.sum).reduce((a,b)=>a+b,0);
  $("#yourBet").textContent = fmt(total);
  $$("#panelMain .cell").forEach(c=>{
    const k=c.dataset.key;
    c.classList.toggle('sel', betState.range[k] > 0);
    c.querySelector('[data-chip]').textContent = betState.range[k].toFixed(2);
  });
  $$("#grid .gcell").forEach(c=>{
    const k=+c.dataset.key;
    c.classList.toggle('sel', betState.sum[k] > 0);
    c.querySelector('[data-chip]').textContent = betState.sum[k].toFixed(2);
  });
}

/* place bet handlers */
let chip=0.10;
$("#chips").addEventListener("click", e=>{
  const el=e.target.closest(".c"); if(!el) return;
  $$("#chips .c").forEach(x=>x.classList.remove('active'));
  el.classList.add('active'); chip=parseFloat(el.dataset.amt); sfx('click');
});
document.addEventListener("click", e=>{
  const cell = e.target.closest(".cell,.gcell"); if(!cell) return;
  if(!balanceReady){ $("#msg").textContent="Loading balance..."; return; }
  if(rolling || phase!=='bet') return;
  if(chip < MIN_BET){ $("#msg").textContent="Chip below min (0.10 ZST)"; sfx('lose'); return; }
  if(balance < chip){ $("#msg").textContent="Insufficient balance"; sfx('lose'); return; }
  if(cell.dataset.type === "range"){
    const key = cell.dataset.key; const ranges = betState.range;
    const otherHas = Object.keys(ranges).some(k=>k!==key && ranges[k]>0);
    if(otherHas && ranges[key]===0){ $("#msg").textContent="Only one Range bet allowed. Clear or add to same Range."; sfx('lose'); return; }
    balance -= chip; betState.range[key]+=chip; lastBet={type:"range",key,amt:chip};
  } else {
    const k = +cell.dataset.key; balance -= chip; betState.sum[k]+=chip; lastBet={type:"sum",key:k,amt:chip};
  }
  sfx('click'); refreshUI();
});

/* controls */
$("#again").onclick = ()=>{ if(!balanceReady){ $("#msg").textContent="Loading balance..."; return; } if(!lastBet){ $("#msg").textContent="No last bet"; return; } if(rolling || phase!=='bet') return;
  const lb = lastBet; if(balance < lb.amt){ $("#msg").textContent="Insufficient balance"; sfx('lose'); return; } balance -= lb.amt;
  if(lb.type==="range") betState.range[lb.key]+=lb.amt; else betState.sum[lb.key]+=lb.amt; sfx('click'); refreshUI();
};
$("#dbl").onclick = ()=>{ if(!balanceReady){ $("#msg").textContent="Loading balance..."; return; } if(rolling || phase!=='bet') return; const spent = Object.values(betState.range).reduce((a,b)=>a+b,0)+Object.values(betState.sum).reduce((a,b)=>a+b,0);
  if(spent<=0){ $("#msg").textContent="Place a bet first"; return; } if(balance<spent){ $("#msg").textContent="Insufficient balance"; sfx('lose'); return; }
  balance -= spent; for(const k in betState.range) betState.range[k]*=2; for(const k in betState.sum) betState.sum[k]*=2; sfx('click'); refreshUI();
};
$("#clr").onclick = ()=>{ if(!balanceReady){ $("#msg").textContent="Loading balance..."; return; } if(rolling || phase!=='bet') return; const refund = Object.values(betState.range).reduce((a,b)=>a+b,0)+Object.values(betState.sum).reduce((a,b)=>a+b,0);
  balance += refund; betState={ range:{down:0,seven:0,up:0}, sum:Object.fromEntries(Array.from({length:11},(_,i)=>[i+2,0])) }; lastBet=null; sfx('click'); refreshUI();
};

/* timer + round flow */
function tick(){ $("#timer").textContent = String(timer).padStart(2,"0"); if(timer<=0){ if(phase==='bet'){ startPreRoll(); return;} if(phase==='preroll'){ startRoll(); return;} } timer--; tId=setTimeout(tick,1000); }
function startBet(){ clearTimeout(tId); phase='bet'; timer=BET_TIME; $("#msg").textContent="Bet phase…"; hideMultipliers(); refreshUI(); tick(); }
function startPreRoll(){ clearTimeout(tId); phase='preroll'; timer = PREROLL_MIN + Math.floor(Math.random()*(PREROLL_MAX-PREROLL_MIN+1)); $("#msg").textContent="Preparing roll…"; maybeActivateMultipliers(); tick(); }

/* Outcome selection with NEW win chance logic */
function selectOutcomeBiased(){
  const totalBets = Object.values(betState.range).reduce((a,b)=>a+b,0) + Object.values(betState.sum).reduce((a,b)=>a+b,0);
  
  // Check if user has placed any bets
  const hasUserBet = totalBets > 0;
  
  // Set win chance percentages based on whether user has bet or not
  const categoryTargets = hasUserBet 
    ? { normal:70, small:60, medium:30, big:15 }  // When user has bets
    : { normal:80, small:80, medium:80, big:80 }; // When user is just watching
  
  const payouts = {};
  for(let s=2;s<=12;s++){
    let p = 0;
    if(s<7 && betState.range.down>0) p += betState.range.down * (PAY_RANGE.down + 1) * (multipliers.down || 1);
    if(s===7 && betState.range.seven>0) p += betState.range.seven * (PAY_RANGE.seven + 1) * (multipliers.seven || 1);
    if(s>7 && betState.range.up>0) p += betState.range.up * (PAY_RANGE.up + 1) * (multipliers.up || 1);
    if(betState.sum[s] > 0) p += betState.sum[s] * (PAY_SUM[s] + 1) * (multipliers[String(s)] || 1);
    payouts[s] = p;
  }
  
  const weights={}; let totalWeight=0;
  for(let s=2;s<=12;s++){
    const p = payouts[s];
    let w = 1 / (1 + p);
    let appliedMult = 1;
    if(s<7 && betState.range.down>0) appliedMult = multipliers.down || 1;
    if(s===7 && betState.range.seven>0) appliedMult = multipliers.seven || 1;
    if(s>7 && betState.range.up>0) appliedMult = multipliers.up || 1;
    if(betState.sum[s] > 0) appliedMult = Math.max(appliedMult, multipliers[String(s)] || 1);
    
    let tier='normal';
    if(appliedMult <=1) tier='normal'; 
    else if(appliedMult<=9) tier='small'; 
    else if(appliedMult<=25) tier='medium'; 
    else tier='big';
    
    const catFactor = categoryTargets[tier]/100;
    w = w * (0.2 + 0.8 * catFactor);
    
    if(totalBets > 0 && p > totalBets * 5) w *= 0.15;
    
    weights[s] = Math.max(w, 0.0001);
    totalWeight += weights[s];
  }
  
  const r = Math.random() * totalWeight; let cum=0;
  for(let s=2;s<=12;s++){ cum += weights[s]; if(r<=cum) return +s; }
  return 7;
}

/* multipliers */
function hideMultipliers(){
  multipliers={};
  $$(".mul").forEach(m=>{ m.classList.remove('show'); m.textContent='×?'; });
  const mb=$("#multBanner"); mb.classList.remove('show','glitch','thunder'); mb.style.display='none';
}
function assignMultipliers(){
  multipliers={};
  const keys=["down","seven","up",..."23456789101112".match(/..?/g).filter(Boolean)];
  const picks=new Set(); const count=3 + Math.floor(Math.random()*4);
  while(picks.size < count) picks.add(keys[Math.floor(Math.random()*keys.length)]);
  picks.forEach(k=>{
    const p = Math.random(); let m;
    if(p < 0.55) m = Math.floor(2 + Math.random()*8);
    else if(p < 0.85) m = Math.floor(10 + Math.random()*16);
    else m = Math.floor(30 + Math.random()*(MULT_MAX-29));
    multipliers[k] = m;
  });
  $$("#panelMain .cell").forEach(c=>{
    const k=c.dataset.key; const b=c.querySelector('[data-mul]');
    if(multipliers[k]){ b.textContent='×'+multipliers[k]; b.classList.add('show'); } else b.classList.remove('show');
  });
  $$("#grid .gcell").forEach(c=>{
    const k=c.dataset.key; const b=c.querySelector('[data-mul]');
    if(multipliers[k]){ b.textContent='×'+multipliers[k]; b.classList.add('show'); } else b.classList.remove('show');
  });
}
function maybeActivateMultipliers(){
  if(multCooldown > 0){ multCooldown--; hideMultipliers(); return; }
  if(Math.random() < 0.5){ hideMultipliers(); multCooldown = 1 + Math.floor(Math.random()*2); return; }
  assignMultipliers();
  const mb=$("#multBanner"); mb.style.display='block'; mb.classList.add('show');
  mb.textContent = "Multiplication Active";
  const eff = Math.random() < 0.5 ? 'glitch' : 'thunder'; mb.classList.add(eff);
  sfx('zap'); setTimeout(()=>mb.classList.remove('glitch','thunder'), 1200);
}

/* settle */
async function settle(sum){
  $("#sumBoard").textContent = `Result: ${sum}`;
  pushHist(sum);
  const totalBet = Object.values(betState.range).reduce((a,b)=>a+b,0) + Object.values(betState.sum).reduce((a,b)=>a+b,0);
  let win = 0;
  if(sum<7 && betState.range.down>0){ const base = PAY_RANGE.down+1; const m = multipliers.down||1; win += betState.range.down * base * m; }
  if(sum===7 && betState.range.seven>0){ const base = PAY_RANGE.seven+1; const m = multipliers.seven||1; win += betState.range.seven * base * m; }
  if(sum>7 && betState.range.up>0){ const base = PAY_RANGE.up+1; const m = multipliers.up||1; win += betState.range.up * base * m; }
  for(let s=2;s<=12;s++){ if(betState.sum[s]>0 && s===sum){ const base=PAY_SUM[s]+1; const m = multipliers[String(s)]||1; win += betState.sum[s] * base * m; } }
  
  // compute net result for the player (positive = net win, negative = net loss)
  const net = Math.round((win - totalBet) * 100) / 100;
  updateLastResultDisplay(net);
  
  // Apply balance change to server
  try {
    const newBalance = await applyGameSettlement(net, 'dice_game');
    if (newBalance !== null) {
      balance = newBalance;
      if(win>0){ $("#msg").textContent = `You won: ${fmt(win)} | New balance ${fmt(balance)}`; sfx('win'); coinRain(); } else { $("#msg").textContent = `You lost: ${fmt(totalBet)} | New balance ${fmt(balance)}`; sfx('lose'); }
    } else {
      $("#msg").textContent = "Settlement failed. Please refresh and try again.";
      sfx('lose');
      return; // Don't proceed with game reset if settlement failed
    }
  } catch (error) {
    console.error('Settlement error:', error);
    $("#msg").textContent = "Network error during settlement. Please refresh.";
    sfx('lose');
    return; // Don't proceed with game reset if settlement failed
  }
  
  $("#tx").textContent = String(++tx);
  betState = { range:{down:0,seven:0,up:0}, sum:Object.fromEntries(Array.from({length:11},(_,i)=>[i+2,0])) };
  lastBet=null; refreshUI(); rolling=false; hideMultipliers(); startBet();
}

/* update last result element (ADDED) */
function updateLastResultDisplay(net){
  const el = $("#lastResult");
  const sign = net > 0 ? '+' : (net < 0 ? '' : '');
  el.textContent = (net>0?'+':'') + (Math.abs(net)).toFixed(2) + ' ZST';
  el.classList.remove('up','down','neutral');
  if(net>0) el.classList.add('up');
  else if(net<0) el.classList.add('down');
  else el.classList.add('neutral');
}

/* roll flow */
function startRoll(){
  if(rolling) return; rolling=true; clearTimeout(tId); phase='rolling'; $("#msg").textContent='Rolling…';
  const chosenSum = selectOutcomeBiased(); // 2..12
  const pairs=[];
  for(let a=1;a<=6;a++) for(let b=1;b<=6;b++) if(a+b===chosenSum) pairs.push([a,b]);
  const [a,b] = rnd(pairs);
  const d1=$("#d1"), d2=$("#d2");
  animateDiceToFaces(d1,d2,a,b,()=>settle(a+b));
}

/* coin rain */
function coinRain(){ const count=22+Math.floor(Math.random()*20); for(let i=0;i<count;i++){ const c=document.createElement('div'); c.className='coin'; const startX=Math.random()*window.innerWidth; c.style.left=startX+'px'; document.body.appendChild(c); const dur=1100+Math.random()*900; const start=performance.now(); (function anim(t0){ function frame(t){ const p=(t-t0)/dur; if(p>=1){ c.remove(); return;} const y = p*(window.innerHeight+40); const x = startX + Math.sin(p*10)*12 + (Math.random()*40-20)*p; c.style.transform=`translate(${x}px, ${y}px)`; c.style.opacity = 1-p; requestAnimationFrame(frame);} requestAnimationFrame(frame);} )(start);} }

/* avatar fly coin (cosmetic) */
function avatarFlyToBet(){ const avEls=$$(".av"); if(avEls.length===0) return; const src=rnd(avEls); const betCells=$$(".cell,.gcell"); if(betCells.length===0) return; const dst=rnd(betCells); const r1=src.getBoundingClientRect(), r2=dst.getBoundingClientRect(); const coin=document.createElement('div'); coin.className='flycoin'; coin.style.left=(r1.left + r1.width/2)+'px'; coin.style.top=(r1.top + r1.height/2)+'px'; document.body.appendChild(coin); const dur=600+Math.random()*500; const start=performance.now(); function anim(t){ const p=(t-start)/dur; if(p>=1){ coin.remove(); return; } const x=r1.left + (r2.left - r1.left)*p + Math.sin(p*6)*10; const y=r1.top + (r2.top - r1.top)*p - Math.sin(p*3)*8; coin.style.transform = `translate(${x}px, ${y}px)`; requestAnimationFrame(anim); } requestAnimationFrame(anim); }

/* history push (sum) */
function pushHist(sum){ const hist=$("#hist"); const d=document.createElement('div'); d.className='h '+(sum<7?'down':sum>7?'up':'mid'); d.textContent=sum; hist.prepend(d); while(hist.childElementCount>18) hist.lastChild.remove(); }

/* init */
function init(){
  buildDie($("#d1")); buildDie($("#d2"));
  // initial orientation to show distinct faces
  $("#d1").style.transform = ori[3]; $("#d2").style.transform = ori[4];
  balanceReady = true; // Enable betting after initialization
  refreshUI(); startBet();
  setInterval(()=>{ if(phase==='bet') avatarFlyToBet(); },1200);
  const avImgs = ["https://i.pravatar.cc/64?img=12","https://i.pravatar.cc/64?img=32","https://i.pravatar.cc/64?img=5","https://i.pravatar.cc/64?img=7"];
  setInterval(()=>{ $$(".av").forEach(img=>{ if(Math.random()<0.4) img.src=rnd(avImgs); }); },3500);

  // initialize last result display to neutral 0
  updateLastResultDisplay(0);
}

/* prevent pinch/zoom */
document.addEventListener('gesturestart', e=>e.preventDefault());
document.addEventListener('gesturechange', e=>e.preventDefault());
document.addEventListener('gestureend', e=>e.preventDefault());

/* start */
// init() is now called from 7up7down.html after balance is loaded