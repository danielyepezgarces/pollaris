import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['minutesInput'];

    connect() {
        this.initializePicker();
    }

    initializePicker() {
        // 1. Ocultar el input numérico original de minutos
        this.minutesInputTarget.style.display = 'none';

        // 2. Crear el nuevo input de tipo time (HH:MM)
        this.timeInput = document.createElement('input');
        this.timeInput.type = 'time';
        this.timeInput.className = 'input--time';
        this.timeInput.style.maxWidth = '150px';
        this.timeInput.style.display = 'block';
        
        // 3. Convertir el valor actual de minutos a formato HH:MM
        const initialMinutes = parseInt(this.minutesInputTarget.value, 10);
        if (!isNaN(initialMinutes) && initialMinutes > 0) {
            this.timeInput.value = this.minutesToTimeStr(initialMinutes);
        } else {
            this.timeInput.value = '01:00'; // Por defecto 1 hora si está vacío
            this.minutesInputTarget.value = 60;
        }

        // 4. Escuchar cambios en el input de tiempo para actualizar el input original
        this.timeInput.addEventListener('change', () => this.updateMinutes());
        this.timeInput.addEventListener('input', () => this.updateMinutes());

        // 5. Insertar el input de tiempo justo al lado o después del input oculto
        this.minutesInputTarget.parentNode.insertBefore(this.timeInput, this.minutesInputTarget.nextSibling);
    }

    minutesToTimeStr(minutes) {
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        return `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}`;
    }

    updateMinutes() {
        const timeVal = this.timeInput.value;
        if (!timeVal) {
            this.minutesInputTarget.value = '';
            return;
        }

        const [hours, minutes] = timeVal.split(':').map(Number);
        const totalMinutes = (hours * 60) + minutes;

        // Validar que la duración sea al menos de 1 minuto
        if (totalMinutes > 0) {
            this.minutesInputTarget.value = totalMinutes;
        } else {
            this.minutesInputTarget.value = 1;
            this.timeInput.value = '00:01';
        }
    }
}
