import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';

const paths = execFileSync('git', ['ls-files', '--cached', '--others', '--exclude-standard', '*.php'], { encoding: 'utf8' })
    .trim()
    .split(/\r?\n/)
    .filter((path) => path && !path.endsWith('.blade.php'));

function wrapType(text, prefix, width = 100) {
    const parts = [];
    let current = text;
    while (prefix.length + current.length > width) {
        let split = -1;
        let quoted = false;
        for (let index = 0; index < current.length; index++) {
            if (current[index] === "'") quoted = !quoted;
            if (!quoted && current[index] === ',' && prefix.length + index < width) split = index + 1;
        }
        if (split < 0) break;
        parts.push(current.slice(0, split));
        current = current.slice(split).trimStart();
        prefix = '     ';
    }
    parts.push(current);
    return parts;
}

for (const path of paths) {
    const original = readFileSync(path, 'utf8');
    const lines = original.split('\n');
    const output = [];
    for (const line of lines) {
        const content = line.replace(/\r$/, '');
        if (content.length <= 120 || !/array\{|@phpstan-type/.test(content) || !/^\s*\/\*\*?\s|^\s*\*/.test(content)) {
            output.push(content);
            continue;
        }
        const match = content.match(/^(\s*)(\/\*\*|\*)(\s*)(.*?)(\s*\*\/)?$/);
        if (!match) {
            output.push(content);
            continue;
        }
        const [, indent, marker, , body, closing] = match;
        const firstPrefix = `${indent} * `;
        const pieces = wrapType(body.trim(), firstPrefix, 100);
        if (pieces.length === 1) {
            output.push(content);
            continue;
        }
        if (marker === '/**') output.push(`${indent}/**`);
        for (const [index, piece] of pieces.entries()) {
            output.push(`${indent} * ${index === 0 ? '' : '    '}${piece}`);
        }
        if (closing) output.push(`${indent} */`);
    }
    const updated = output.join('\n');
    if (updated !== original) writeFileSync(path, updated);
}
