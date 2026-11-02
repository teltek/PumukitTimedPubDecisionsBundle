import { Controller } from '@hotwired/stimulus';

const parse = (value) => new Date(value).getTime();

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['bar', 'mark'];

    static values = { start: String, end: String };

    connect() {
        this.from = parse(this.startValue);
        this.span = parse(this.endValue) - this.from;
        this.markTargets.forEach((mark) => {
            mark.style.left = `${this.percent(parse(mark.dataset.date))}%`;
        });
        this.barTargets.forEach((bar) => {
            const left = this.percent(parse(bar.dataset.from));
            const right = this.percent(parse(bar.dataset.to));
            bar.style.left = `${left}%`;
            bar.style.width = `${Math.max(right - left, 0)}%`;
            bar.classList.toggle('rounded-start-0', parse(bar.dataset.from) < this.from);
            bar.classList.toggle('rounded-end-0', parse(bar.dataset.to) > this.from + this.span);
        });
    }

    filter(event) {
        event.currentTarget.requestSubmit();
    }

    percent(time) {
        return Math.min(Math.max((time - this.from) / this.span, 0), 1) * 100;
    }
}
