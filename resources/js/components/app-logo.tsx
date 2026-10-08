export default function AppLogo() {
    return (
        <>
            <svg
                width="30"
                height="30"
                viewBox="0 0 30 30"
                className="shrink-0"
                aria-hidden="true"
            >
                <rect width="30" height="30" rx="7" fill="var(--indigo)" />
                <polygon points="15,5 25,15 15,25 5,15" fill="var(--saff)" />
                <polygon
                    points="15,10 20,15 15,20 10,15"
                    fill="var(--indigo)"
                />
            </svg>
            <div className="ml-1 grid flex-1 text-left">
                <span className="truncate text-base leading-tight font-bold">
                    Carpet system
                </span>
                <span className="truncate text-xs leading-tight text-muted-foreground">
                    Hangu and Kohat
                </span>
            </div>
        </>
    );
}
