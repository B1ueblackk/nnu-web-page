// === 倒计时 ===
const target = new Date('2025-09-10T09:00:00');
function updateClock () {
    const timerEl = document.getElementById('time-left');
    if (!timerEl) return;  // 倒计时元素不存在时直接返回

    const now   = new Date();
    const diff  = target - now;

    // 已到目标时间
    if (diff <= 0) {
        timerEl.textContent = '开幕啦！';
        return;
    }

    const days = Math.floor(diff / 864e5);
    const hrs  = Math.floor(diff / 36e5) % 24;
    const mins = Math.floor(diff / 6e4)  % 60;
    const secs = Math.floor(diff / 1000) % 60;

    timerEl.textContent =
        `${days} 天 ${String(hrs).padStart(2, '0')}:` +
        `${String(mins).padStart(2, '0')}:` +
        `${String(secs).padStart(2, '0')}`;
}
setInterval(updateClock,1000);updateClock();


(function(){
    const slides=[...document.querySelectorAll('#carousel .slide')],
        btns=[...document.querySelectorAll('.carousel-nav button')];
    let idx=0; function show(i){
        slides[idx].classList.remove('active'); btns[idx].classList.remove('active');
        idx=i; slides[idx].classList.add('active'); btns[idx].classList.add('active');
    }
    btns.forEach((b,i)=>b.addEventListener('click',()=>show(i)));
    setInterval(()=>show((idx+1)%slides.length),4000);
})();
/* ...其余脚本同原... */