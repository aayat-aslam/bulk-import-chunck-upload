import { Head } from '@inertiajs/react';
import Navigation from '@/Components/Navigation';

export default function AppLayout({ auth, header, children }) {
    return (
        <div className="min-h-screen bg-black-50">
            <Navigation user={auth?.user} />

            {/* Page Heading */}
            {header && (
                <header className="bg-black shadow">
                    <div className="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}

            {/* Page Content */}
            <main className="py-6">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    {children}
                </div>
            </main>
        </div>
    );
}
