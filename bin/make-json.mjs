#!/usr/bin/env node
/**
 * Converts fastcloudwp-{locale}.po files into a single JSON file per locale
 * in the Jed 1.x format that @wordpress/i18n's setLocaleData() expects.
 *
 * Run via: npm run make-json
 */

import { readFileSync, writeFileSync, readdirSync } from 'fs';
import { join, basename } from 'path';

const LANG_DIR = new URL('../languages', import.meta.url).pathname;

function parsePo(content) {
    const entries = [];
    let current = {};
    let inMsgstr = false;
    let inMsgid = false;
    let inMsgidPlural = false;
    let pluralIndex = -1;

    for (const raw of content.split('\n')) {
        const line = raw.trimEnd();

        if (line.startsWith('msgid_plural ')) {
            current.msgid_plural = line.slice(13).replace(/^"|"$/g, '');
            inMsgidPlural = true;
            inMsgid = false;
            inMsgstr = false;
        } else if (line.startsWith('msgid ')) {
            if (current.msgid !== undefined) entries.push(current);
            current = { msgid: line.slice(6).replace(/^"|"$/g, ''), msgstr: [] };
            inMsgid = true;
            inMsgstr = false;
            inMsgidPlural = false;
            pluralIndex = -1;
        } else if (/^msgstr\[(\d+)\]/.test(line)) {
            pluralIndex = parseInt(line.match(/\[(\d+)\]/)[1]);
            current.msgstr[pluralIndex] = line.replace(/^msgstr\[\d+\]\s*/, '').replace(/^"|"$/g, '');
            inMsgstr = true;
            inMsgid = false;
        } else if (line.startsWith('msgstr ')) {
            current.msgstr = [line.slice(7).replace(/^"|"$/g, '')];
            inMsgstr = true;
            inMsgid = false;
        } else if (line.startsWith('"') && line.endsWith('"')) {
            const chunk = line.slice(1, -1);
            if (inMsgstr) {
                if (pluralIndex >= 0) {
                    current.msgstr[pluralIndex] = (current.msgstr[pluralIndex] || '') + chunk;
                } else {
                    current.msgstr[0] = (current.msgstr[0] || '') + chunk;
                }
            } else if (inMsgid) {
                current.msgid += chunk;
            } else if (inMsgidPlural) {
                current.msgid_plural += chunk;
            }
        } else if (line === '' || line.startsWith('#')) {
            inMsgstr = false;
            inMsgid = false;
            inMsgidPlural = false;
            pluralIndex = -1;
        }
    }
    if (current.msgid !== undefined) entries.push(current);
    return entries;
}

function buildJed(entries, locale) {
    // Extract header entry (msgid "")
    const header = entries.find((e) => e.msgid === '');
    const pluralForms = header?.msgstr[0]?.match(/plural-forms:\s*([^;]+(?:;[^;]+)*)/i)?.[0] ?? '';

    const messages = {
        '': {
            domain: 'messages',
            lang: locale,
            'plural-forms': pluralForms,
        },
    };

    for (const entry of entries) {
        if (!entry.msgid || !entry.msgstr.length) continue;
        // Skip untranslated strings
        if (entry.msgstr.every((s) => !s)) continue;

        if (entry.msgid_plural) {
            messages[entry.msgid] = entry.msgstr;
        } else {
            messages[entry.msgid] = [entry.msgstr[0]];
        }
    }

    return {
        'translation-revision-date': new Date().toISOString(),
        generator: 'fastcloudwp/bin/make-json.mjs',
        domain: 'messages',
        locale_data: { messages },
    };
}

const poFiles = readdirSync(LANG_DIR).filter(
    (f) => f.startsWith('fastcloud-offload-media-') && f.endsWith('.po')
);

if (poFiles.length === 0) {
    console.log('No .po files found in languages/');
    process.exit(0);
}

for (const file of poFiles) {
    const locale = basename(file, '.po').replace('fastcloud-offload-media-', '');
    const content = readFileSync(join(LANG_DIR, file), 'utf8');
    const entries = parsePo(content);
    const jed = buildJed(entries, locale);

    const out = join(LANG_DIR, `fastcloud-offload-media-${locale}-js.json`);
    writeFileSync(out, JSON.stringify(jed, null, '\t'), 'utf8');
    console.log(`Generated ${out} (${Object.keys(jed.locale_data.messages).length - 1} strings)`);
}
