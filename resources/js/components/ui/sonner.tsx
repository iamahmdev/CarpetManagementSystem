import { useFlashToast } from '@/hooks/use-flash-toast';
import { useAppearance } from '@/hooks/use-appearance';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="bottom-right"
            style={
                {
                    '--normal-bg': 'var(--toast-bg)',
                    '--normal-text': 'var(--toast-text)',
                    '--normal-border': 'var(--toast-bg)',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
