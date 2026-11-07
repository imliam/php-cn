/** Regenerate differential tests against the pinned upstream TypeScript implementation.
 * Usage: node scripts/generate-upstream-fixtures.mjs /path/to/tailwind-merge
 * Node.js 22.13+; no npm dependencies. See resources/upstream/tailwind-merge-LICENSE.md.
 */
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { stripTypeScriptTypes } from 'node:module'
import { resolve } from 'node:path'
import { runInNewContext } from 'node:vm'
import { execFileSync } from 'node:child_process'

const revision = 'bceabfd95eab05553d15c5368b2684de697a84eb'
const checkout = process.argv[2]
if (!checkout) throw new Error('Provide a tailwind-merge checkout at ' + revision)
if (execFileSync('git', ['rev-parse', 'HEAD'], { cwd: checkout, encoding: 'utf8' }).trim() !== revision) {
    throw new Error('Checkout must be pinned to ' + revision)
}
const modules = new Map()
function loadModule(path) {
    const fullPath = resolve(checkout, 'src/lib', path + '.ts')
    if (modules.has(fullPath)) return modules.get(fullPath)
    const exported = []
    const code = stripTypeScriptTypes(readFileSync(fullPath, 'utf8'), { mode: 'strip' })
        .replace(/^import\s+\{([\s\S]*?)\}\s+from\s+'([^']+)'/gm, (_, names, from) => `const {${names}} = loadModule('${from}')`)
        .replace(/\bexport (const|function) (\w+)/g, (_, kind, name) => {
            exported.push(name)
            return `${kind} ${name}`
        })
    const module = runInNewContext(code + ';\n({' + exported.join(',') + '})', { loadModule })
    modules.set(fullPath, module)
    return module
}
const { twMerge } = loadModule('tw-merge')
const { getDefaultConfig } = loadModule('default-config')
const { createClassGroupUtils } = loadModule('class-group-utils')
const validators = loadModule('validators')
const config = getDefaultConfig()
const { getClassGroupId } = createClassGroupUtils(config)
const candidateValues = [
    '', '0', '1', '2', '3.5', '1/2', '2.5/3', 'full', 'none', 'auto', 'sm', 'xl',
    'red-500', 'blue-500', 'black', 'custom', '25%',
    '[10px]', '[20px]', '[50%]', '[2]', '[3]', '[var(--custom)]',
    '[length:--a]', '[length:--b]', '[number:2]', '[number:3]',
    '[family-name:Arial]', '[family-name:Georgia]', '[weight:400]', '[weight:500]',
    '[position:10px]', '[position:20px]', '[percentage:20%]', '[bg-size:20px]',
    '[size:10px]', '[size:20px]', '[shadow:--a]', '[shadow:--b]',
    '[0_0_#000]', '[0_2px_4px_black]', '[url(a)]', '[url(b)]',
    '(--a)', '(--b)', '(length:--a)', '(length:--b)', '(number:--a)',
    '(family-name:--a)', '(family-name:--b)', '(weight:--a)', '(weight:--b)',
    '(position:--a)', '(position:--b)', '(size:--a)', '(size:--b)',
    '(image:--a)', '(image:--b)', '(shadow:--a)', '(shadow:--b)',
    '@container/sidebar', '@container-size/main', '@container-normal/sidebar',
]
function expand(definitions, prefix = '') {
    return definitions.flatMap(definition => {
        if (typeof definition === 'string') return [prefix + definition]
        if (typeof definition === 'function') {
            if (definition.isThemeGetter) return expand(definition(config.theme), prefix)
            const candidates = definition === validators.isAny ? ['red-500', 'blue-500'] : definition === validators.isAnyNonArbitrary ? ['custom', 'other'] : candidateValues
            return candidates.filter(definition).slice(0, 2).map(value => prefix + value)
        }
        return Object.entries(definition).flatMap(([part, values]) => expand(values, prefix + part + '-'))
    }).map(value => value.endsWith('-') ? value.slice(0, -1) : value)
}
const fixtures = []
const seen = new Set()
function add(label, input) {
    if (seen.has(input)) return
    seen.add(input)
    fixtures.push([label, input, twMerge(input)])
}
const classes = Object.fromEntries(Object.entries(config.classGroups).map(([id, definitions]) => {
    const samples = [...new Set(expand(definitions))].filter(value => getClassGroupId(value) === id)
    if (!samples.length) throw new Error('No recognized class sample for ' + id)
    for (const sample of samples) {
        const other = samples.find(value => value !== sample) ?? sample
        add(id, sample + ' ' + other)
        add(id, other + ' ' + sample)
        add(id + ' important', sample + '! ' + other + '!')
        add(id + ' variant', 'hover:' + sample + ' hover:' + other)
    }
    return [id, samples]
}))
for (const [id, conflicts] of Object.entries(config.conflictingClassGroups)) {
    for (const conflict of conflicts) {
        const first = classes[id][0]
        const second = classes[conflict][0]
        add(id + ' conflicts with ' + conflict, first + ' ' + second)
        add(conflict + ' conflicts with ' + id, second + ' ' + first)
    }
}
// Capture the upstream hand-written validator cases as independent assertions.
const validatorFixtures = []
const captures = Object.fromEntries(Object.entries(validators).map(([name, validate]) => [name, (input = '') => ({ name, input, actual: validate(input) })]))
runInNewContext(stripTypeScriptTypes(readFileSync(resolve(checkout, 'tests/validators.test.ts'), 'utf8'), { mode: 'strip' })
    .replace(/^import .*\n/gm, ''), {
    validators: captures,
    test: (_name, callback) => callback(),
    expect: ({ name, input, actual }) => ({ toBe: expected => {
        if (actual !== expected) throw new Error('Upstream validator assertion failed: ' + name)
        validatorFixtures.push([name === 'isAny' ? 'AnyValueValidator' : name.slice(2) + 'Validator', input, expected])
    } }),
})
mkdirSync('tests/Fixtures', { recursive: true })
const metadata = { upstream: 'tailwind-merge', version: '3.6.0', revision }
const serialize = (header, cases) => JSON.stringify(header, null, 2).slice(0, -2) + ',\n  \"cases\": [\n' + cases.map(row => '    ' + JSON.stringify(row)).join(',\n') + '\n  ]\n}\n'
writeFileSync('tests/Fixtures/tailwind-4-merges.json', serialize({ ...metadata, classGroups: Object.keys(classes).length }, fixtures))
writeFileSync('tests/Fixtures/tailwind-4-validators.json', serialize(metadata, validatorFixtures))
console.log(`Generated ${fixtures.length} merge cases across ${Object.keys(classes).length} class groups and ${validatorFixtures.length} validator cases.`)
