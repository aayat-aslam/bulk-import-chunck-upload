import { Link } from '@inertiajs/react';

export default function Navigation({ user }) {
    return (
        <nav className="bg-white border-b border-gray-100">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex justify-between h-16">
                    <div className="flex">
                        <div className="shrink-0 flex items-center">
                            <Link href="/">
                                <span className="text-xl font-bold text-gray-800">Product Importer</span>
                            </Link>
                        </div>
                        <div className="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">
                            <NavLink href="/dashboard" active={route().current('dashboard')}>
                                Dashboard
                            </NavLink>
                            <NavLink href="/products" active={route().current('products.index')}>
                                View Products
                            </NavLink>
                            <NavLink href="/products/import" active={route().current('products.import')}>
                                Import Products
                            </NavLink>
                            <NavLink href="/uploads" active={route().current('uploads')}>
                                File Uploader
                            </NavLink>
                        </div>
                    </div>

                    <div className="hidden sm:flex sm:items-center sm:ml-6">
                        {user ? (
                            <div className="ml-3 relative">
                                <div className="flex items-center">
                                    <span className="text-sm text-gray-700 mr-4">
                                        Welcome, {user.name}
                                    </span>
                                    <Link
                                        href={route('logout')}
                                        method="post"
                                        as="button"
                                        className="text-sm text-gray-700 hover:text-gray-900"
                                    >
                                        Log Out
                                    </Link>
                                </div>
                            </div>
                        ) : (
                            <>
                                <Link
                                    href={route('login')}
                                    className="text-sm text-gray-700 hover:text-gray-900"
                                >
                                    Log in
                                </Link>

                                <Link
                                    href={route('register')}
                                    className="ml-4 text-sm text-gray-700 hover:text-gray-900"
                                >
                                    Register
                                </Link>
                            </>
                        )}
                    </div>

                    {/* Mobile menu button */}
                    <div className="-mr-2 flex items-center sm:hidden">
                        <button className="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <span className="sr-only">Open main menu</span>
                            <svg
                                className="block h-6 w-6"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            {/* Mobile menu */}
            <div className="sm:hidden">
                <div className="pt-2 pb-3 space-y-1">
                    <MobileNavLink href="/dashboard" active={route().current('dashboard')}>
                        Dashboard
                    </MobileNavLink>
                    <MobileNavLink href="/products" active={route().current('products.index')}>
                        View Products
                    </MobileNavLink>
                    <MobileNavLink href="/products/import" active={route().current('products.import')}>
                        Import Products
                    </MobileNavLink>
                    <MobileNavLink href="/uploads" active={route().current('uploads')}>
                        File Uploader
                    </MobileNavLink>
                </div>
                {user && (
                    <div className="pt-4 pb-3 border-t border-gray-200">
                        <div className="px-4">
                            <div className="text-base font-medium text-gray-800">{user.name}</div>
                            <div className="text-sm font-medium text-gray-500">{user.email}</div>
                        </div>
                        <div className="mt-3 space-y-1">
                            <MobileNavLink href={route('logout')} method="post" as="button">
                                Log Out
                            </MobileNavLink>
                        </div>
                    </div>
                )}
            </div>
        </nav>
    );
}

function NavLink({ active, children, ...props }) {
    return (
        <Link
            {...props}
            className={`inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none ${
                active
                    ? 'border-indigo-400 text-gray-900 focus:border-indigo-700'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:text-gray-700 focus:border-gray-300'
            }`}
        >
            {children}
        </Link>
    );
}

function MobileNavLink({ active, children, ...props }) {
    return (
        <Link
            {...props}
            className={`block pl-3 pr-4 py-2 border-l-4 text-base font-medium ${
                active
                    ? 'bg-indigo-50 border-indigo-400 text-indigo-700 focus:outline-none focus:bg-indigo-100 focus:border-indigo-700'
                    : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800'
            }`}
        >
            {children}
        </Link>
    );
}
