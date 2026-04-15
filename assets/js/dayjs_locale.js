// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Modified by Daniel Yepez Garces on 2026-04-15:
// - Migrated database backend from PostgreSQL to MariaDB for Toolforge deployment
// - Added Wikimedia login support
// - Removed local username/password authentication
// - Added multilingual survey support
// - Added user timezone display for survey times when different from server UTC

import dayjsLocaleCs from 'dayjs/locale/cs';
import dayjsLocaleEs from 'dayjs/locale/es';
import dayjsLocaleDe from 'dayjs/locale/de';
import dayjsLocaleFr from 'dayjs/locale/fr';
import dayjsLocaleGl from 'dayjs/locale/gl';
import dayjsLocaleHu from 'dayjs/locale/hu';
import dayjsLocaleIt from 'dayjs/locale/it';
import dayjsLocaleOc from 'dayjs/locale/oc-lnc';
import dayjsLocalePt from 'dayjs/locale/pt';
import dayjsLocalePtBr from 'dayjs/locale/pt-br';
import dayjsLocaleUk from 'dayjs/locale/uk';
import dayjsPluginLocalizedFormat from 'dayjs/plugin/localizedFormat';

export function setDayjsLocale (dayjs) {
    dayjs.extend(dayjsPluginLocalizedFormat);

    const lang = (document.documentElement.lang || 'en').toLowerCase().replace(/_/g, '-');

    if (lang.startsWith('cs')) {
        dayjs.locale('cs');
    } else if (lang.startsWith('de')) {
        dayjs.locale('de');
    } else if (lang.startsWith('es')) {
        dayjs.locale('es');
    } else if (lang.startsWith('fr')) {
        dayjs.locale('fr');
    } else if (lang.startsWith('gl')) {
        dayjs.locale('gl');
    } else if (lang.startsWith('hu')) {
        dayjs.locale('hu');
    } else if (lang.startsWith('it')) {
        dayjs.locale('it');
    } else if (lang.startsWith('oc')) {
        dayjs.locale('oc-lnc');
    } else if (lang.startsWith('pt-br')) {
        dayjs.locale('pt-br');
    } else if (lang.startsWith('pt')) {
        dayjs.locale('pt');
    } else if (lang.startsWith('uk')) {
        dayjs.locale('uk');
    } else {
        dayjs.locale('en');
    }
}
