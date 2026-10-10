const code39Patterns: Record<string, string> = {
    '0': '000110100',
    '1': '100100001',
    '2': '001100001',
    '3': '101100000',
    '4': '000110001',
    '5': '100110000',
    '6': '001110000',
    '7': '000100101',
    '8': '100100100',
    '9': '001100100',
    A: '100001001',
    B: '001001001',
    C: '101001000',
    D: '000011001',
    E: '100011000',
    F: '001011000',
    G: '000001101',
    H: '100001100',
    I: '001001100',
    J: '000011100',
    K: '100000011',
    L: '001000011',
    M: '101000010',
    N: '000010011',
    O: '100010010',
    P: '001010010',
    Q: '000000111',
    R: '100000110',
    S: '001000110',
    T: '000010110',
    U: '110000001',
    V: '011000001',
    W: '111000000',
    X: '010010001',
    Y: '110010000',
    Z: '011010000',
    '-': '010000101',
    '*': '010010100',
};

export function PieceBarcode({
    code,
    height = 44,
}: {
    code: string;
    height?: number;
}) {
    const characters = `*${code}*`;
    const bars: { x: number; width: number }[] = [];
    let position = 4;

    [...characters].forEach((character, characterIndex) => {
        const pattern = code39Patterns[character];
        if (!pattern) {
            throw new Error(`Unsupported Code 39 character: ${character}`);
        }

        [...pattern].forEach((width, index) => {
            const barWidth = width === '1' ? 3 : 1;
            if (index % 2 === 0) {
                bars.push({ x: position, width: barWidth });
            }
            position += barWidth;
        });

        if (characterIndex < characters.length - 1) {
            position += 1;
        }
    });

    return (
        <svg
            className="ledger-piece-barcode"
            role="img"
            aria-label={`Barcode ${code}`}
            viewBox={`0 0 ${position + 4} ${height}`}
            preserveAspectRatio="none"
        >
            {bars.map((bar, index) => (
                <rect
                    key={index}
                    x={bar.x}
                    y="0"
                    width={bar.width}
                    height={height}
                    fill="currentColor"
                />
            ))}
        </svg>
    );
}
