import { onLivewire } from './action-bridge.js';

const FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
const AR_DIGITS = '٠١٢٣٤٥٦٧٨٩';

function toLatinDigits(str) {
    return String(str || '').replace(/[۰-۹٠-٩]/g, (c) => {
        const fi = FA_DIGITS.indexOf(c);
        return fi !== -1 ? String(fi) : String(AR_DIGITS.indexOf(c));
    });
}

function toDisplayDigits(val, locale) {
    const s = String(val);
    if (!locale || !locale.startsWith('fa')) return s;
    return s.replace(/\d/g, (d) => FA_DIGITS[Number(d)]);
}

function pad2(n) {
    return String(n).padStart(2, '0');
}

function pad4(n) {
    return String(n).padStart(4, '0');
}

function isGregorianLeap(gy) {
    return (gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0;
}

function gregorianDaysInMonth(gy, gm) {
    return [0, 31, isGregorianLeap(gy) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][gm] || 30;
}

// Standard Jalaali 33-year break cycle (matches morilog/jalali)
const JALALI_BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];

function jalCal(jy) {
    const bl = JALALI_BREAKS.length;
    const gy = jy + 621;
    let leapJ = -14;
    let jp = JALALI_BREAKS[0];
    let jump = 0;
    for (let i = 1; i < bl; i++) {
        const jm = JALALI_BREAKS[i];
        jump = jm - jp;
        if (jy < jm) break;
        leapJ = leapJ + Math.floor(jump / 33) * 8 + Math.floor((jump % 33) / 4);
        jp = jm;
    }
    let n = jy - jp;
    leapJ = leapJ + Math.floor(n / 33) * 8 + Math.floor(((n % 33) + 3) / 4);
    if (jump % 33 === 4 && jump - n === 4) leapJ += 1;
    const leapG = Math.floor(gy / 4) - Math.floor((Math.floor(gy / 100) + 1) * 3 / 4) - 150;
    const march = 20 + leapJ - leapG;
    if (jump - n < 6) n = n - jump + Math.floor((jump + 4) / 33) * 33;
    let leap = (((n + 1) % 33) - 1) % 4;
    if (leap === -1) leap = 4;
    return { leap: leap === 0, gy, march };
}

function isJalaliLeap(jy) {
    return jalCal(jy).leap;
}

function jalaliDaysInMonth(jy, jm) {
    if (jm <= 6) return 31;
    if (jm <= 11) return 30;
    return isJalaliLeap(jy) ? 30 : 29;
}

function g2d(gy, gm, gd) {
    let d = Math.floor((gy + Math.floor((gm - 8) / 6) + 100100) * 1461 / 4)
        + Math.floor((153 * ((gm + 9) % 12) + 2) / 5)
        + gd - 34840408;
    d = d - Math.floor(Math.floor((gy + 100100 + Math.floor((gm - 8) / 6)) / 100) * 3 / 4) + 752;
    return d;
}

function d2g(jdn) {
    let j = 4 * jdn + 139361631;
    j = j + Math.floor(Math.floor((4 * jdn + 183187720) / 146097) * 3 / 4) * 4 - 3908;
    const i = Math.floor((j % 1461) / 4) * 5 + 308;
    const gd = Math.floor((i % 153) / 5) + 1;
    const gm = (Math.floor(i / 153) % 12) + 1;
    const gy = Math.floor(j / 1461) - 100100 + Math.floor((8 - gm) / 6);
    return [gy, gm, gd];
}

function jalaliToGregorian(jy, jm, jd) {
    const r = jalCal(jy);
    const jdn1f = g2d(r.gy, 3, r.march);
    const k = (jm <= 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186) + (jd - 1);
    return d2g(jdn1f + k);
}

function gregorianToJalali(gy, gm, gd) {
    const jdn = g2d(gy, gm, gd);
    let jy = gy - 621;
    let r = jalCal(jy);
    let jdn1f = g2d(r.gy, 3, r.march);
    let k = jdn - jdn1f;
    if (k < 0) {
        jy -= 1;
        r = jalCal(jy);
        jdn1f = g2d(r.gy, 3, r.march);
        k = jdn - jdn1f;
    }
    const jm = k < 186 ? 1 + Math.floor(k / 31) : 7 + Math.floor((k - 186) / 30);
    const jd = 1 + (k < 186 ? k % 31 : (k - 186) % 30);
    return [jy, jm, jd];
}

function weekdayOfDate(system, y, m, d) {
    const [gy, gm, gd] = system === 'jalali' ? jalaliToGregorian(y, m, d) : [y, m, d];
    return new Date(Date.UTC(gy, gm - 1, gd)).getUTCDay(); // 0=Sun..6=Sat
}

function parseDateValue(raw, system) {
    const clean = toLatinDigits(raw).trim();
    const m = clean.match(/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/);
    if (m) {
        const y = Number(m[1]);
        const mo = Number(m[2]);
        const d = Number(m[3]);
        if (system === 'jalali' && y >= 1900) {
            return gregorianToJalali(y, mo, d);
        }
        if (system === 'gregorian' && y < 1700) {
            return jalaliToGregorian(y, mo, d);
        }
        return [y, mo, d];
    }
    const now = new Date();
    const gy = now.getFullYear();
    const gm = now.getMonth() + 1;
    const gd = now.getDate();
    return system === 'jalali' ? gregorianToJalali(gy, gm, gd) : [gy, gm, gd];
}

function setInputValue(input, value) {
    input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

const MONTHS = {
    fa: {
        jalali: ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'],
        gregorian: ['ژانویه', 'فوریه', 'مارس', 'آوریل', 'مه', 'ژوئن', 'ژوئیه', 'اوت', 'سپتامبر', 'اکتبر', 'نوامبر', 'دسامبر'],
    },
    en: {
        jalali: ['Farvardin', 'Ordibehesht', 'Khordad', 'Tir', 'Mordad', 'Shahrivar', 'Mehr', 'Aban', 'Azar', 'Dey', 'Bahman', 'Esfand'],
        gregorian: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
    },
};

const WEEKDAYS = {
    fa: ['ی', 'د', 'س', 'چ', 'پ', 'ج', 'ش'],
    en: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
};

let activePopover = null;
let activeInput = null;

function closePicker() {
    if (activePopover) {
        activePopover.remove();
        activePopover = null;
    }
    if (activeInput) {
        activeInput.removeAttribute('aria-expanded');
        activeInput = null;
    }
}

function positionPopover(popover, anchor) {
    const rect = anchor.getBoundingClientRect();
    const root = anchor.closest('[data-kw-calendar]');
    const rtl = root && getComputedStyle(root).direction === 'rtl';
    const popWidth = 268;
    let top = rect.bottom + 6;
    let left = rtl ? rect.right - popWidth : rect.left;
    if (left + popWidth > window.innerWidth - 12) left = window.innerWidth - popWidth - 12;
    if (left < 12) left = 12;
    if (top + 290 > window.innerHeight - 12 && rect.top > 290) {
        top = rect.top - 286;
    }
    popover.style.top = `${Math.max(8, top)}px`;
    popover.style.left = `${left}px`;
}

function openDatePicker(input, trigger) {
    if (activeInput === input && activePopover) {
        closePicker();
        return;
    }
    closePicker();
    const root = input.closest('[data-kw-calendar]');
    const system = (root && root.dataset.system) || 'gregorian';
    const locale = (root && root.dataset.locale) || 'en';
    const lang = locale.startsWith('fa') ? 'fa' : 'en';
    const firstWd = system === 'jalali' ? 6 : 0; // Saturday for Jalali, Sunday for Gregorian
    let [viewY, viewM, selD] = parseDateValue(input.value, system);
    const [selY, selM] = [viewY, viewM];
    const now = new Date();
    const [todayY, todayM, todayD] = system === 'jalali'
        ? gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate())
        : [now.getFullYear(), now.getMonth() + 1, now.getDate()];

    const pop = document.createElement('div');
    pop.className = 'kw-picker card card-border';
    pop.dataset.kwPart = 'datepicker-popover';
    pop.setAttribute('role', 'dialog');
    pop.setAttribute('aria-label', lang === 'fa' ? 'انتخاب تاریخ' : 'Choose date');
    if (root) {
        pop.dir = getComputedStyle(root).direction;
    }

    function renderGrid() {
        const daysCount = system === 'jalali' ? jalaliDaysInMonth(viewY, viewM) : gregorianDaysInMonth(viewY, viewM);
        const startWd = weekdayOfDate(system, viewY, viewM, 1);
        const offset = (startWd - firstWd + 7) % 7;
        const monthLabel = (MONTHS[lang][system] || MONTHS.en.gregorian)[viewM - 1] || '';

        pop.replaceChildren();
        const head = document.createElement('div');
        head.className = 'kw-picker__head';
        const makeChev = (isNext) => {
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('class', isNext ? 'kw-chev kw-chev--next' : 'kw-chev');
            svg.setAttribute('viewBox', '0 0 24 24');
            svg.setAttribute('width', '16');
            svg.setAttribute('height', '16');
            svg.setAttribute('aria-hidden', 'true');
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', 'M15 6l-6 6 6 6');
            path.setAttribute('fill', 'none');
            path.setAttribute('stroke', 'currentColor');
            path.setAttribute('stroke-width', '2');
            path.setAttribute('stroke-linecap', 'round');
            path.setAttribute('stroke-linejoin', 'round');
            svg.appendChild(path);
            return svg;
        };
        const prevBtn = document.createElement('button');
        prevBtn.type = 'button';
        prevBtn.className = 'kw-picker__nav btn btn-xs btn-ghost btn-square';
        prevBtn.appendChild(makeChev(false));
        prevBtn.setAttribute('aria-label', lang === 'fa' ? 'ماه قبل' : 'Previous month');
        prevBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            viewM -= 1;
            if (viewM < 1) { viewM = 12; viewY -= 1; }
            renderGrid();
        });

        const title = document.createElement('span');
        title.className = 'kw-picker__title';
        title.textContent = `${monthLabel} ${toDisplayDigits(viewY, locale)}`;

        const nextBtn = document.createElement('button');
        nextBtn.type = 'button';
        nextBtn.className = 'kw-picker__nav btn btn-xs btn-ghost btn-square';
        nextBtn.appendChild(makeChev(true));
        nextBtn.setAttribute('aria-label', lang === 'fa' ? 'ماه بعد' : 'Next month');
        nextBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            viewM += 1;
            if (viewM > 12) { viewM = 1; viewY += 1; }
            renderGrid();
        });

        head.append(prevBtn, title, nextBtn);

        const wds = document.createElement('div');
        wds.className = 'kw-picker__weekdays';
        for (let i = 0; i < 7; i++) {
            const wd = (firstWd + i) % 7;
            const sp = document.createElement('span');
            sp.className = 'kw-picker__wd';
            sp.textContent = WEEKDAYS[lang][wd];
            wds.appendChild(sp);
        }

        const grid = document.createElement('div');
        grid.className = 'kw-picker__days';
        for (let i = 0; i < offset; i++) {
            const empty = document.createElement('span');
            empty.className = 'kw-picker__empty';
            grid.appendChild(empty);
        }
        for (let d = 1; d <= daysCount; d++) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'kw-picker__day';
            if (viewY === selY && viewM === selM && d === selD) btn.classList.add('is-selected');
            if (viewY === todayY && viewM === todayM && d === todayD) btn.classList.add('is-today');
            btn.textContent = toDisplayDigits(d, locale);
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                setInputValue(input, `${pad4(viewY)}/${pad2(viewM)}/${pad2(d)}`);
                closePicker();
                input.focus();
            });
            grid.appendChild(btn);
        }

        const foot = document.createElement('div');
        foot.className = 'kw-picker__foot';
        const todayBtn = document.createElement('button');
        todayBtn.type = 'button';
        todayBtn.className = 'kw-picker__today btn btn-xs btn-ghost';
        todayBtn.textContent = lang === 'fa' ? 'امروز' : 'Today';
        todayBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            setInputValue(input, `${pad4(todayY)}/${pad2(todayM)}/${pad2(todayD)}`);
            closePicker();
            input.focus();
        });
        foot.appendChild(todayBtn);

        pop.append(head, wds, grid, foot);
    }

    renderGrid();
    (root || document.body).appendChild(pop);
    positionPopover(pop, trigger || input);
    activePopover = pop;
    activeInput = input;
    input.setAttribute('aria-expanded', 'true');
}

function openTimePicker(input, trigger) {
    if (activeInput === input && activePopover) {
        closePicker();
        return;
    }
    closePicker();
    const root = input.closest('[data-kw-calendar]');
    const locale = (root && root.dataset.locale) || 'en';
    const lang = locale.startsWith('fa') ? 'fa' : 'en';
    const current = toLatinDigits(input.value).trim();

    const pop = document.createElement('div');
    pop.className = 'kw-picker kw-picker--time card card-border';
    pop.dataset.kwPart = 'timepicker-popover';
    pop.setAttribute('role', 'listbox');
    pop.setAttribute('aria-label', lang === 'fa' ? 'انتخاب ساعت' : 'Choose time');
    if (root) {
        pop.dir = getComputedStyle(root).direction;
    }

    let selectedEl = null;
    for (let h = 0; h < 24; h++) {
        for (const m of [0, 30]) {
            const val = `${pad2(h)}:${pad2(m)}`;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'kw-picker__slot';
            btn.setAttribute('role', 'option');
            if (val === current) {
                btn.classList.add('is-selected');
                btn.setAttribute('aria-selected', 'true');
                selectedEl = btn;
            }
            btn.textContent = toDisplayDigits(val, locale);
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                setInputValue(input, val);
                closePicker();
                input.focus();
            });
            pop.appendChild(btn);
        }
    }

    (root || document.body).appendChild(pop);
    positionPopover(pop, trigger || input);
    activePopover = pop;
    activeInput = input;
    input.setAttribute('aria-expanded', 'true');
    if (selectedEl) {
        requestAnimationFrame(() => selectedEl.scrollIntoView({ block: 'center' }));
    }
}

document.addEventListener('click', (e) => {
    const trigger = e.target.closest && e.target.closest('[data-kw-picker-btn]');
    if (trigger) {
        e.preventDefault();
        e.stopPropagation();
        const wrap = trigger.closest('[data-kw-picker-wrap]');
        const input = wrap && wrap.querySelector('input');
        if (!input) return;
        if (trigger.dataset.kwPickerBtn === 'time') openTimePicker(input, wrap);
        else openDatePicker(input, wrap);
        return;
    }

    const input = e.target.closest && e.target.closest('input[data-kw-picker]');
    if (input) {
        const wrap = input.closest('[data-kw-picker-wrap]') || input;
        if (input.dataset.kwPicker === 'time') openTimePicker(input, wrap);
        else openDatePicker(input, wrap);
        return;
    }

    if (activePopover && !activePopover.contains(e.target)) {
        closePicker();
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && activePopover) {
        e.stopPropagation();
        const inp = activeInput;
        closePicker();
        if (inp) inp.focus();
    }
});

onLivewire(() => {
    window.Livewire.hook('morphed', closePicker);
});
