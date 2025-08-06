// === 倒计时 ===
const target = new Date('2025-10-09T00:00:00');
function updateClock () {
    const timerEl = document.getElementById('time-left');
    if (!timerEl) return;  // 倒计时元素不存在时直接返回

    const now   = new Date();
    const diff  = target - now;

    // 已到目标时间
    if (diff <= 0) {
        timerEl.textContent = '会议已开始';
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

// 移动端导航菜单
document.addEventListener('DOMContentLoaded', function() {
    // 创建菜单按钮
    const menuToggle = document.createElement('button');
    menuToggle.className = 'menu-toggle';
    menuToggle.innerHTML = '☰';
    document.body.appendChild(menuToggle);

    // 创建遮罩层
    const overlay = document.createElement('div');
    overlay.className = 'nav-overlay';
    document.body.appendChild(overlay);

    const nav = document.querySelector('.nav');
    const body = document.body;

    // 切换菜单状态
    function toggleMenu() {
        nav.classList.toggle('active');
        overlay.classList.toggle('active');
        body.classList.toggle('nav-open');
    }

    // 点击菜单按钮
    menuToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleMenu();
    });

    // 点击遮罩层关闭菜单
    overlay.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleMenu();
    });

    // 点击导航链接后关闭菜单
    document.querySelectorAll('.nav a').forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                toggleMenu();
            }
        });
    });

    // 处理窗口大小改变
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            nav.classList.remove('active');
            overlay.classList.remove('active');
            body.classList.remove('nav-open');
        }
    });

    // 处理滑动关闭
    let touchStartX = 0;
    let touchEndX = 0;

    nav.addEventListener('touchstart', function(e) {
        touchStartX = e.changedTouches[0].screenX;
    }, false);

    nav.addEventListener('touchend', function(e) {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    }, false);

    function handleSwipe() {
        if (touchEndX - touchStartX > 50) { // 向右滑动超过50px
            if (nav.classList.contains('active')) {
                toggleMenu();
            }
        }
    }
});


// 滚动
window.addEventListener('DOMContentLoaded', () => {
    const ul = document.querySelector('.credits ul');
    const container = document.querySelector('.credits');

    if (ul && container) {
        const listHeight = ul.scrollHeight;
        const containerHeight = container.clientHeight;
        const distance = listHeight + containerHeight;
        const speed = 40; // px/sec
        const duration = distance / speed;

        ul.style.animationDuration = `${duration}s`;
        ul.style.setProperty('--scroll-distance', `${listHeight}px`);
    }
});

// 初始化倒计时并每秒更新
updateCountdown();
setInterval(updateCountdown, 1000);

// 登录功能
function login() {
    window.location.href = 'login/index.html';
}