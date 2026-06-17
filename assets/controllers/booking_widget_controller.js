import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        endpoint: String,
    };

    connect() {
        this.element.dataset.bookingReady = '1';
    }

    async open(event) {
        event.preventDefault();
        const endpoint = this.endpointValue || this.element.getAttribute('href') || '';
        if (!endpoint) {
            return;
        }

        window.location.href = endpoint;
    }
}
