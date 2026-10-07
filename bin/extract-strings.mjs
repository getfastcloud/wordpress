#!/usr/bin/env node
/**
 * Extracts translatable strings from Vue and TypeScript source files and
 * merges them as POT entries into languages/fastcloud-offload-media.pot.
 *
 * Run via: npm run make-pot
 */

import { readFileSync, writeFileSync, readdirSync, statSync } from 'fs';
import { join, extname, relative } from 'path';

const PLUGIN_DIR = new URL('..', import.meta.url).pathname;
const SRC_DIR = join(PLUGIN_DIR, 'src');
const POT_FILE = join(PLUGIN_DIR, 'languages', 'fastcloud-offload-media.pot');

function walkDir(dir) {
	const files = [];
	for (const entry of readdirSync(dir)) {
		const full = join(dir, entry);
		if (statSync(full).isDirectory()) {
			files.push(...walkDir(full));
		} else {
			const ext = extname(entry);
			if (ext === '.vue' || ext === '.ts') {
				files.push(full);
			}
		}
	}
	return files;
}

/**
 * Extract all __() and _n() calls along with any preceding translator comment.
 * Returns an array of { comment, singular, plural, context } objects.
 */
function extractCalls(source) {
	const results = [];

	// Matches optional translator comment + __( or _n( call
	// Handles both single and double quotes
	const pattern =
		/(?:\/\*\s*(translators:[^*]*)\*\/\s*)?__\s*\(\s*(['"])((?:\\.|(?!\2)[^\\])*)\2\s*,\s*['"]fastcloud-offload-media['"]\s*\)/gs;

	let m;
	while ((m = pattern.exec(source)) !== null) {
		results.push({
			comment: m[1] ? m[1].trim() : null,
			singular: m[3].replace(/\\'/g, "'").replace(/\\"/g, '"'),
		});
	}

	return results;
}

function extractFromVue(content) {
	const parts = [];

	// Script block
	const scriptMatch = content.match(/<script[^>]*lang="ts"[^>]*>([\s\S]*?)<\/script>/);
	if (scriptMatch) {
		parts.push(scriptMatch[1]);
	}

	// Template block — pull attribute bindings and interpolation expressions
	const templateMatch = content.match(/<template>([\s\S]*?)<\/template>/);
	if (templateMatch) {
		// Extract content from {{ }} expressions and :" bindings
		const tpl = templateMatch[1];
		const exprPattern = /(?:{{([\s\S]*?)}}|(?::[\w-]+=|v-[\w-]+=)"([\s\S]*?)")/g;
		let em;
		while ((em = exprPattern.exec(tpl)) !== null) {
			parts.push(em[1] || em[2]);
		}
	}

	return parts.join('\n');
}

function buildPotEntry({ comment, singular }, relPath) {
	const lines = [];
	if (comment) {
		lines.push(`#. ${comment}`);
	}
	lines.push(`#: ${relPath}`);
	lines.push(`msgid "${singular.replace(/"/g, '\\"')}"`);
	lines.push('msgstr ""');
	return lines.join('\n');
}

// Collect all strings from source files
const entries = new Map(); // keyed by msgid to deduplicate

for (const file of walkDir(SRC_DIR)) {
	const content = readFileSync(file, 'utf8');
	const ext = extname(file);
	const source = ext === '.vue' ? extractFromVue(content) : content;
	const relPath = relative(PLUGIN_DIR, file);

	for (const call of extractCalls(source)) {
		if (!entries.has(call.singular)) {
			entries.set(call.singular, { ...call, relPath });
		}
	}
}

// Load the existing POT file (generated from PHP by wp i18n make-pot)
let pot;
try {
	pot = readFileSync(POT_FILE, 'utf8');
} catch {
	console.error(`Error: POT file not found at ${POT_FILE}`);
	console.error('Run: ddev wp i18n make-pot ... to generate it first.');
	process.exit(1);
}

// Strip any JS entries added by a previous run so reruns are always idempotent.
// Matches: optional #. comment + #: src/... reference + msgid + msgstr block.
pot = pot.replace(/\n\n(?:#\.[^\n]*\n)?#: src\/[^\n]*\nmsgid "[^\n]*"\nmsgstr ""/g, '');
pot = pot.trimEnd();

// Skip strings already present via PHP extraction to avoid duplicate msgids.
for (const [msgid] of entries) {
	const escaped = msgid.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	if (new RegExp(`msgid "${escaped}"`).test(pot)) {
		entries.delete(msgid);
	}
}

// Append JS-only entries.
if (entries.size > 0) {
	const newEntries = [...entries.values()]
		.map((e) => buildPotEntry(e, e.relPath))
		.join('\n\n');
	pot = pot + '\n\n' + newEntries + '\n';
} else {
	pot = pot + '\n';
}

writeFileSync(POT_FILE, pot, 'utf8');

console.log(
	`Merged ${entries.size} JS string(s) from ${walkDir(SRC_DIR).length} source files into ${POT_FILE}`
);
