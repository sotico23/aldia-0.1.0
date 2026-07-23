import type { PropsWithChildren } from 'react';

export default function SettingsLayout({ children }: PropsWithChildren) {
    return (
        <div className="mx-auto w-full max-w-4xl px-4 py-6">
            <div className="flex flex-col space-y-8">
                <section className="space-y-8">{children}</section>
            </div>
        </div>
    );
}
