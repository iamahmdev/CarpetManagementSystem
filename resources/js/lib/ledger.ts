export function formatMoney(amount: number): string {
    return `PKR ${new Intl.NumberFormat('en-US').format(amount)}`;
}

export function formatDay(value: string): string {
    return new Intl.DateTimeFormat('en-GB', {
        day: 'numeric',
        month: 'short',
    }).format(new Date(value));
}

export function formatDateTime(value: string): string {
    const date = new Date(value);
    const day = formatDay(value);
    const time = new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).format(date);

    return `${day}, ${time}`;
}

export function localDateTimeInput(): string {
    const now = new Date();
    const offset = now.getTimezoneOffset() * 60_000;
    return new Date(now.getTime() - offset).toISOString().slice(0, 16);
}
