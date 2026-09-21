/**
 * Prueft, ob das gebaute Storefront-Bundle zu den Quellen passt.
 *
 * Die Tests laufen bewusst gegen das Build-Artefakt. Ist es veraltet, testen sie den
 * falschen Code - das ist in der Entwicklung schon passiert und faellt sonst erst auf,
 * wenn ein Test aus unerklaerlichen Gruenden rot wird.
 *
 *   node bundle-freshness.js           prueft
 *   node bundle-freshness.js --stamp   schreibt den aktuellen Stand fest (nach dem Build)
 */
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const PLUGIN_ROOT = path.resolve(__dirname, '..', '..');
const SOURCE_DIR = path.join(PLUGIN_ROOT, 'src/Resources/app/storefront/src');
const STAMP_FILE = path.join(__dirname, 'bundle.sha256');
const BUNDLE = path.join(
    PLUGIN_ROOT,
    'src/Resources/app/storefront/dist/storefront/js/shop4-google-tag-manager/shop4-google-tag-manager.js',
);

function sourceFiles(dir) {
    return fs.readdirSync(dir, { withFileTypes: true })
        .sort((a, b) => a.name.localeCompare(b.name))
        .flatMap((entry) => {
            const full = path.join(dir, entry.name);
            return entry.isDirectory() ? sourceFiles(full) : [full];
        });
}

function sourceHash() {
    const hash = crypto.createHash('sha256');
    sourceFiles(SOURCE_DIR).forEach((file) => {
        hash.update(path.relative(PLUGIN_ROOT, file).replace(/\\/g, '/'));
        hash.update(fs.readFileSync(file));
    });
    return hash.digest('hex');
}

const current = sourceHash();

if (process.argv.includes('--stamp')) {
    fs.writeFileSync(STAMP_FILE, current + '\n');
    console.log('Bundle-Stand festgeschrieben:', current.substring(0, 12));
    process.exit(0);
}

if (!fs.existsSync(BUNDLE)) {
    console.error('Bundle fehlt. Vorher bin/build-storefront.sh laufen lassen.');
    process.exit(1);
}

if (!fs.existsSync(STAMP_FILE)) {
    console.error(`${STAMP_FILE} fehlt. Nach dem Build "npm run stamp" ausfuehren.`);
    process.exit(1);
}

const expected = fs.readFileSync(STAMP_FILE, 'utf8').trim();

if (expected !== current) {
    console.error('Das gebaute Bundle passt nicht zu den Storefront-Quellen.');
    console.error(`  erwartet: ${expected.substring(0, 12)}`);
    console.error(`  aktuell:  ${current.substring(0, 12)}`);
    console.error('bin/build-storefront.sh laufen lassen, das dist/-Bundle zurueckholen und');
    console.error('anschliessend "npm run stamp" ausfuehren.');
    process.exit(1);
}

console.log('Bundle passt zu den Quellen.');
