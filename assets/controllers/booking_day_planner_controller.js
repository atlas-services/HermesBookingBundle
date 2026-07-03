import '../styles/booking.css';
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['monthLabel', 'daysGrid', 'status', 'weekdayBar'];

    static values = {
        apiUrl: String,
        bookingKey: String,
    };

    connect() {
        const now = new Date();
        this.currentYear = now.getFullYear();
        this.currentMonth = now.getMonth() + 1;
        this.dayStates = new Map();
        this.dateMin = '';
        this.dateMax = '';
        this.lastClickedDate = '';
        this.lastActionOpen = true;
        this.isLoading = false;

        this.loadState();
    }

    async previousMonth() {
        this.currentMonth -= 1;
        if (this.currentMonth < 1) {
            this.currentMonth = 12;
            this.currentYear -= 1;
        }
        this.renderMonth();
    }

    async nextMonth() {
        this.currentMonth += 1;
        if (this.currentMonth > 12) {
            this.currentMonth = 1;
            this.currentYear += 1;
        }
        this.renderMonth();
    }

    async openAll() {
        await this.postAction({ action: 'set_all', open: true, ...this.currentMonthScope() });
    }

    async closeAll() {
        await this.postAction({ action: 'set_all', open: false, ...this.currentMonthScope() });
    }

    async openWeekday(event) {
        const weekday = Number.parseInt(event.params.weekday, 10);
        if (!Number.isFinite(weekday)) {
            return;
        }
        await this.postAction({ action: 'set_weekdays', weekdays: [weekday], open: true, ...this.currentMonthScope() });
    }

    async closeWeekday(event) {
        const weekday = Number.parseInt(event.params.weekday, 10);
        if (!Number.isFinite(weekday)) {
            return;
        }
        await this.postAction({ action: 'set_weekdays', weekdays: [weekday], open: false, ...this.currentMonthScope() });
    }

    async onlyWeekday(event) {
        const weekday = Number.parseInt(event.params.weekday, 10);
        if (!Number.isFinite(weekday)) {
            return;
        }
        await this.postAction({ action: 'only_weekdays', weekdays: [weekday], ...this.currentMonthScope() });
    }

    currentMonthScope() {
        return {
            year: this.currentYear,
            month: this.currentMonth,
        };
    }

    async loadState() {
        this.setStatus('loading');
        try {
            const response = await fetch(this.apiUrlValue, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                throw new Error('load_failed');
            }
            const payload = await response.json();
            this.applyPayload(payload);
            this.renderMonth();
            this.setStatus('ready');
        } catch {
            this.setStatus('error');
        }
    }

    applyPayload(payload) {
        if (payload.dateMin) {
            this.dateMin = payload.dateMin;
        }
        if (payload.dateMax) {
            this.dateMax = payload.dateMax;
        }
        if (Array.isArray(payload.days)) {
            this.dayStates = new Map();
            payload.days.forEach((day) => {
                this.dayStates.set(day.date, day.state);
            });
        }
    }

    renderMonth() {
        if (this.hasMonthLabelTarget) {
            this.monthLabelTarget.textContent = this.formatMonthLabel(this.currentYear, this.currentMonth);
        }
        if (!this.hasDaysGridTarget) {
            return;
        }

        this.daysGridTarget.innerHTML = '';

        const firstDay = new Date(this.currentYear, this.currentMonth - 1, 1);
        const daysInMonth = new Date(this.currentYear, this.currentMonth, 0).getDate();
        const startOffset = (firstDay.getDay() + 6) % 7;

        for (let i = 0; i < startOffset; i += 1) {
            this.daysGridTarget.appendChild(this.createEmptyCell());
        }

        for (let day = 1; day <= daysInMonth; day += 1) {
            const date = this.toDateString(this.currentYear, this.currentMonth, day);
            const state = this.dayStates.get(date) || 'out_of_range';
            this.daysGridTarget.appendChild(this.createDayButton(date, day, state));
        }
    }

    createEmptyCell() {
        const empty = document.createElement('div');
        empty.className = 'hermes-booking-picker__day hermes-booking-picker__day--empty';
        empty.setAttribute('aria-hidden', 'true');
        return empty;
    }

    createDayButton(date, dayNumber, state) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'hermes-booking-picker__day hermes-booking-day-planner__day';
        button.textContent = String(dayNumber);
        button.dataset.date = date;
        button.dataset.state = state;

        button.classList.add(`hermes-booking-day-planner__day--${state}`);

        if (state === 'open' || state === 'closed') {
            button.addEventListener('click', (event) => this.onDayClick(date, state, event));
        } else {
            button.disabled = true;
        }

        return button;
    }

    async onDayClick(date, state, event) {
        if (this.isLoading) {
            return;
        }

        if (event.shiftKey && this.lastClickedDate) {
            await this.postAction({
                action: 'set_range',
                from: this.lastClickedDate,
                to: date,
                open: this.lastActionOpen,
            });
            this.lastClickedDate = date;
            return;
        }

        const open = state !== 'open';
        this.lastActionOpen = open;
        await this.postAction({ action: 'toggle', date, open });
        this.lastClickedDate = date;
    }

    async postAction(body) {
        this.isLoading = true;
        this.setStatus('saving');
        try {
            const response = await fetch(this.apiUrlValue, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(body),
            });
            if (!response.ok) {
                throw new Error('save_failed');
            }
            const payload = await response.json();
            this.applyPayload(payload);
            this.renderMonth();
            this.setStatus('saved');
        } catch {
            this.setStatus('error');
        } finally {
            this.isLoading = false;
        }
    }

    setStatus(mode) {
        if (!this.hasStatusTarget) {
            return;
        }
        const messages = {
            loading: this.statusTarget.dataset.loading || '',
            saving: this.statusTarget.dataset.saving || '',
            saved: this.statusTarget.dataset.saved || '',
            ready: '',
            error: this.statusTarget.dataset.error || '',
        };
        this.statusTarget.textContent = messages[mode] || '';
    }

    formatMonthLabel(year, month) {
        return new Date(year, month - 1, 1).toLocaleDateString(undefined, {
            month: 'long',
            year: 'numeric',
        });
    }

    toDateString(year, month, day) {
        return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    }
}
