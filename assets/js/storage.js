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

export function getEntry (namespace, key) {
    const storage = getNamespace(namespace);
    const entry = storage[key];
    if (entry !== undefined) {
        return entry;
    } else {
        return null;
    }
}

export function storeEntry (namespace, key, entry) {
    const storage = getNamespace(namespace);
    storage[key] = entry;
    saveNamespace(namespace, storage);
}

export function unstoreEntry (namespace, key) {
    const storage = getNamespace(namespace);
    if (storage[key] != null) {
        delete storage[key];
    }
    saveNamespace(namespace, storage);
}

export function listEntries (namespace) {
    const storage = getNamespace(namespace);
    return Object.entries(storage);
}

export function getNamespace (namespace) {
    const storageName = `storage-${namespace}`;
    const storage = JSON.parse(window.localStorage.getItem(storageName));
    if (isObject(storage)) {
        return storage;
    } else {
        return {};
    }
}

export function saveNamespace (namespace, storage) {
    const storageName = `storage-${namespace}`;
    window.localStorage.setItem(storageName, JSON.stringify(storage));
}

export function deleteNamespace (namespace) {
    const storageName = `storage-${namespace}`;
    window.localStorage.removeItem(storageName);
}

function isObject (variable) {
    return typeof variable === 'object' && !Array.isArray(variable) && variable !== null;
}
