import * as Turbo from '@hotwired/turbo';
import { Application } from '@hotwired/stimulus';

import CalendarController from './controllers/calendar_controller.js';
import CollectionController from './controllers/collection_controller.js';
import ColorSchemeController from './controllers/color_scheme_controller.js';
import CopyToClipboardController from './controllers/copy_to_clipboard_controller.js';
import DatesFormController from './controllers/dates_form_controller.js';
import FormLeaveConfirmationController from './controllers/form_leave_confirmation_controller.js';
import MailtoController from './controllers/mailto_controller.js';
import MyController from './controllers/my_controller.js';
import MyVotesController from './controllers/my_votes_controller.js';
import ModalController from './controllers/modal_controller.js';
import ModalOpenerController from './controllers/modal_opener_controller.js';
import NotificationController from './controllers/notification_controller.js';
import PollPasswordController from './controllers/poll_password_controller.js';
import PollViewController from './controllers/poll_view_controller.js';
import PollTimezoneController from './controllers/poll_timezone_controller.js';
import PrinterController from './controllers/printer_controller.js';
import ProtectedButtonController from './controllers/protected_button_controller.js';
import QRCodeController from './controllers/qr_code_controller.js';
import ScrollboxController from './controllers/scrollbox_controller.js';
import SlotsApplierController from './controllers/slots_applier_controller.js';
import StorageController from './controllers/storage_controller.js';
import TimezoneDisplayController from './controllers/timezone_display_controller.js';
import ToggleRadioController from './controllers/toggle_radio_controller.js';
import PollTableHeadView from './controllers/poll_table_head_view.js';
import FormVoteValidation from './controllers/form_vote_validation.js';
import LocalTimeController from './controllers/local_time_controller.js';

const application = Application.start();
application.register('calendar', CalendarController);
application.register('collection', CollectionController);
application.register('local-time', LocalTimeController);
application.register('color-scheme', ColorSchemeController);
application.register('copy-to-clipboard', CopyToClipboardController);
application.register('dates-form', DatesFormController);
application.register('form-leave-confirmation', FormLeaveConfirmationController);
application.register('mailto', MailtoController);
application.register('modal', ModalController);
application.register('modal-opener', ModalOpenerController);
application.register('my', MyController);
application.register('my-votes', MyVotesController);
application.register('notification', NotificationController);
application.register('poll-password', PollPasswordController);
application.register('poll-view', PollViewController);
application.register('poll-timezone', PollTimezoneController);
application.register('printer', PrinterController);
application.register('protected-button', ProtectedButtonController);
application.register('qr-code', QRCodeController);
application.register('scrollbox', ScrollboxController);
application.register('slots-applier', SlotsApplierController);
application.register('storage', StorageController);
application.register('timezone-display', TimezoneDisplayController);
application.register('toggle-radio', ToggleRadioController);
application.register('poll-table-head-view', PollTableHeadView);
application.register('form-vote-validation', FormVoteValidation);

// Make sure to visit the response when receiving the `turbo:frame-missing` event.
// This happens most of the time on redirection after submitting a form in a modal.
// Otherwise, "Content missing" would be displayed within the modal.
document.addEventListener('turbo:frame-missing', (event) => {
    event.preventDefault();
    event.detail.visit(event.detail.response);
});
