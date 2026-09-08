export function parseRegistrationFieldValue(raw) {
    if (raw === null || raw === undefined) return '—';

    const value = typeof raw === 'string' ? tryParseJson(raw) : raw;

    return formatValue(value);
}

function tryParseJson(text) {
    try {
        return JSON.parse(text);
    } catch {
        return text;
    }
}

function formatValue(value) {
    if (value === null || value === undefined) return '—';
    if (typeof value === 'string') return value;
    if (Array.isArray(value)) return value.map(formatValue).join(', ');
    if (typeof value === 'object') return JSON.stringify(value);

    return String(value);
}
