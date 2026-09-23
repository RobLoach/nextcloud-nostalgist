#!/usr/bin/env node
/**
 * Collect the translatable strings of the app into a template file.
 *
 * Nextcloud reads translations from l10n/<language>.json, which the
 * translation platform produces from this template. Only literal strings
 * are collected: anything passed as a variable cannot be translated, and
 * the app avoids doing that.
 */
import { readdirSync, readFileSync, statSync, writeFileSync, mkdirSync } from 'node:fs'
import { join } from 'node:path'

const ROOTS = ['lib', 'src', 'templates']
const PATTERNS = [
	// t('arcade', 'Some text') in JavaScript
	/\bt\(\s*'arcade'\s*,\s*'((?:[^'\\]|\\.)*)'/g,
	// $l->t('Some text') and $this->l->t('Some text') in PHP
	/\$(?:this->)?l->t\(\s*'((?:[^'\\]|\\.)*)'/g,
]

function walk(path) {
	if (statSync(path).isFile()) {
		return /\.(php|js)$/.test(path) ? [path] : []
	}
	return readdirSync(path).flatMap((entry) => walk(join(path, entry)))
}

const strings = new Map()
for (const file of ROOTS.flatMap(walk)) {
	const content = readFileSync(file, 'utf8')
	for (const pattern of PATTERNS) {
		for (const [, text] of content.matchAll(pattern)) {
			const value = text.replace(/\\'/g, "'")
			strings.set(value, [...(strings.get(value) ?? []), file])
		}
	}
}

const header = `# Translations of the Arcade app.
msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
`
const body = [...strings.entries()]
	.sort(([a], [b]) => a.localeCompare(b))
	.map(([text, files]) => {
		const where = [...new Set(files)].map((file) => `#: ${file}`).join('\n')
		const escaped = text.replace(/\\/g, '\\\\').replace(/"/g, '\\"')
		return `${where}\nmsgid "${escaped}"\nmsgstr ""\n`
	})
	.join('\n')

mkdirSync('translationfiles/templates', { recursive: true })
writeFileSync('translationfiles/templates/arcade.pot', `${header}\n${body}`)
console.info(`${strings.size} strings to translate`)
