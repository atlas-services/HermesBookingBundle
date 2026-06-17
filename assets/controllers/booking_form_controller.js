import '../styles/booking.css';
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'picker',
        'monthLabel',
        'daysGrid',
        'slotsGrid',
        'hint',
        'summary',
        'dateInput',
        'timeInput',
    ];

    static values = {
        slotsUrl: String,
        calendarUrl: String,
        noSlotsMessage: { type: String, default: 'Aucun créneau disponible pour cette date.' },
        selectDayMessage: { type: String, default: 'Choisissez une date dans le calendrier.' },
        selectSlotMessage: { type: String, default: 'Choisissez un créneau horaire.' },
        summaryTemplate: { type: String, default: 'Créneau sélectionné : %date% à %time%' },
    };

    connect() {
        const now = new Date();
        this.currentYear = now.getFullYear();
        this.currentMonth = now.getMonth() + 1;
        this.selectedDate = '';
        this.selectedTime = '';
        this.availableDates = new Set();
        this.dateMin = '';
        this.dateMax = '';

        this.element.addEventListener('submit', (event) => this.validateBeforeSubmit(event));
        this.renderCalendar();
    }

    validateBeforeSubmit(event) {
        if (!this.dateInputTarget.value || !this.timeInputTarget.value) {
            event.preventDefault();
            this.setHint(
                !this.dateInputTarget.value
                    ? this.selectDayMessageValue
                    : this.selectSlotMessageValue,
                true,
            );
        }
    }

    async previousMonth() {
        this.currentMonth -= 1;
        if (this.currentMonth < 1) {
            this.currentMonth = 12;
            this.currentYear -= 1;
        }
        await this.renderCalendar();
    }

    async nextMonth() {
        this.currentMonth += 1;
        if (this.currentMonth > 12) {
            this.currentMonth = 1;
            this.currentYear += 1;
        }
        await this.renderCalendar();
    }

    async renderCalendar() {
        this.monthLabelTarget.textContent = this.formatMonthLabel(this.currentYear, this.currentMonth);
        this.daysGridTarget.innerHTML = '';
        this.setHint(this.selectDayMessageValue);

        const payload = await this.fetchCalendar(this.currentYear, this.currentMonth);
        this.availableDates = new Set(payload.availableDates || []);
        this.dateMin = payload.dateMin || '';
        this.dateMax = payload.dateMax || '';

        const firstDay = new Date(this.currentYear, this.currentMonth - 1, 1);
        const daysInMonth = new Date(this.currentYear, this.currentMonth, 0).getDate();
        const startOffset = (firstDay.getDay() + 6) % 7;

        for (let i = 0; i < startOffset; i += 1) {
            const empty = document.createElement('div');
            empty.className = 'hermes-booking-picker__day hermes-booking-picker__day--empty';
            empty.setAttribute('aria-hidden', 'true');
            this.daysGridTarget.appendChild(empty);
        }

        for (let day = 1; day <= daysInMonth; day += 1) {
            const date = this.toDateString(this.currentYear, this.currentMonth, day);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'hermes-booking-picker__day';
            button.textContent = String(day);

            const isAvailable = this.availableDates.has(date);
            const inRange = this.isInRange(date);

            if (!inRange || !isAvailable) {
                button.classList.add('hermes-booking-picker__day--muted');
                button.disabled = true;
            } else {
                button.classList.add('hermes-booking-picker__day--available');
                button.dataset.date = date;
                button.addEventListener('click', () => this.selectDate(date, button));
            }

            if (date === this.selectedDate) {
                button.classList.add('hermes-booking-picker__day--selected');
            }

            this.daysGridTarget.appendChild(button);
        }

        if (this.selectedDate && this.availableDates.has(this.selectedDate)) {
            await this.loadSlots(this.selectedDate);
        } else {
            this.clearSlots();
        }
    }

    async selectDate(date, button) {
        this.selectedDate = date;
        this.selectedTime = '';
        this.dateInputTarget.value = date;
        this.timeInputTarget.value = '';

        this.daysGridTarget.querySelectorAll('.hermes-booking-picker__day--selected').forEach((el) => {
            el.classList.remove('hermes-booking-picker__day--selected');
        });
        button.classList.add('hermes-booking-picker__day--selected');

        await this.loadSlots(date);
        this.updateSummary();
    }

    async loadSlots(date) {
        this.slotsGridTarget.innerHTML = '';
        this.setHint('Chargement des créneaux…');

        const url = new URL(this.slotsUrlValue, window.location.origin);
        url.searchParams.set('date', date);

        try {
            const response = await fetch(url.toString(), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                this.setHint('Impossible de charger les créneaux.', true);
                return;
            }

            const payload = await response.json();
            const slots = Array.isArray(payload.slots) ? payload.slots : [];

            if (slots.length === 0) {
                this.setHint(this.noSlotsMessageValue, true);
                return;
            }

            if (!this.selectedTime) {
                this.setHint(this.selectSlotMessageValue);
            } else {
                this.setHint('');
            }

            slots.forEach((slot) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'hermes-booking-picker__slot';
                button.textContent = slot;
                button.dataset.time = slot;
                button.addEventListener('click', () => this.selectSlot(slot, button));
                if (slot === this.selectedTime) {
                    button.classList.add('hermes-booking-picker__slot--selected');
                }
                this.slotsGridTarget.appendChild(button);
            });
        } catch {
            this.setHint('Impossible de charger les créneaux.', true);
        }
    }

    selectSlot(time, button) {
        this.selectedTime = time;
        this.timeInputTarget.value = time;

        this.slotsGridTarget.querySelectorAll('.hermes-booking-picker__slot--selected').forEach((el) => {
            el.classList.remove('hermes-booking-picker__slot--selected');
        });
        button.classList.add('hermes-booking-picker__slot--selected');
        this.setHint('');
        this.updateSummary();
    }

    clearSlots() {
        this.slotsGridTarget.innerHTML = '';
        this.summaryTarget.textContent = '';
    }

    updateSummary() {
        if (!this.selectedDate || !this.selectedTime) {
            this.summaryTarget.textContent = '';
            return;
        }

        const formattedDate = this.formatDisplayDate(this.selectedDate);
        this.summaryTarget.textContent = this.summaryTemplateValue
            .replace('%date%', formattedDate)
            .replace('%time%', this.selectedTime);
    }

    async fetchCalendar(year, month) {
        const url = new URL(this.calendarUrlValue, window.location.origin);
        url.searchParams.set('year', String(year));
        url.searchParams.set('month', String(month));

        try {
            const response = await fetch(url.toString(), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return { availableDates: [] };
            }

            return await response.json();
        } catch {
            return { availableDates: [] };
        }
    }

    isInRange(date) {
        if (this.dateMin && date < this.dateMin) {
            return false;
        }
        if (this.dateMax && date > this.dateMax) {
            return false;
        }

        return true;
    }

    toDateString(year, month, day) {
        return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    }

    formatMonthLabel(year, month) {
        const date = new Date(year, month - 1, 1);
        return date.toLocaleDateString(document.documentElement.lang || 'fr-FR', {
            month: 'long',
            year: 'numeric',
        });
    }

    formatDisplayDate(dateString) {
        const [year, month, day] = dateString.split('-').map(Number);
        const date = new Date(year, month - 1, day);

        return date.toLocaleDateString(document.documentElement.lang || 'fr-FR', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
    }

    setHint(message, isError = false) {
        if (!this.hasHintTarget) {
            return;
        }

        this.hintTarget.textContent = message;
        this.hintTarget.classList.toggle('text-danger', isError);
        this.hintTarget.classList.toggle('text-muted', !isError);
    }
}
