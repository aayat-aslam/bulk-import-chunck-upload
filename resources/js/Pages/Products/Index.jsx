import React, { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';

export default function ProductsIndex({ products, filters }) {
    const [loading, setLoading] = useState(false);
    const [perPage, setPerPage] = useState(filters.per_page || 20);

    // Handle page change
    const handlePageChange = (page) => {
        router.get(route('products.index'), { page, per_page: perPage }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['products']
        });
    };

    // Handle per page change
    const handlePerPageChange = (e) => {
        const newPerPage = e.target.value;
        setPerPage(newPerPage);
        router.get(route('products.index'), { per_page: newPerPage }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['products']
        });
    };

    return (
        <AppLayout>
            <Head title="Products" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 bg-white border-b border-gray-200">
                            <h1 className="text-2xl font-bold text-gray-800 mb-6">Our Products</h1>

                            {products.data.length === 0 ? (
                                <div className="text-center py-12">
                                    <p className="text-gray-500">No products found.</p>
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    {products.data.map((product) => (
                                        <div key={product.id} className="border rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                                            <Link href={`/products/${product.sku}`} className="block">
                                                {product.primary_image ? (
                                                    <div className="relative h-48 bg-gray-100 overflow-hidden group">
                                                        <img
                                                            // src={`${product.primary_image?.path?.replace(/\/original$/, '_orig')}`}
                                                            src={`${product.primary_image?.path}`}
                                                            alt={product.primary_image.alt || product.name}
                                                            data-testid={product.primary_image.path}
                                                            className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                                                            srcSet={Object.entries(product.primary_image.variants || {})
                                                                .map(([variant, data]) => `${data.url} ${variant.replace(/\D/g, '')}w`)
                                                                .join(', ')}
                                                            sizes="(max-width: 768px) 100vw, 33vw"
                                                        />
                                                        {product.primary_image.variants && (
                                                            <div className="absolute bottom-2 left-2 bg-black bg-opacity-50 text-white text-xs px-2 py-1 rounded">
                                                                {Object.keys(product.primary_image.variants).join(' / ')}
                                                            </div>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <div className="h-48 bg-gray-200 flex items-center justify-center">
                                                        <span className="text-gray-500">No image</span>
                                                    </div>
                                                )}

                                                <div className="p-4">
                                                    <h3 className="font-semibold text-lg mb-1">{product.name}</h3>
                                                    <p className="text-gray-600 text-sm mb-2 line-clamp-2">
                                                        {product.description}
                                                    </p>
                                                    <div className="flex justify-between items-center mt-3">
                                                        <span className="font-bold text-gray-900">${product.price}</span>
                                                        <span className="text-xs text-gray-500">
                                                            {product.images_count} {product.images_count === 1 ? 'image' : 'images'}
                                                        </span>
                                                    </div>
                                                </div>
                                            </Link>
                                        </div>
                                    ))}
                                </div>
                            )}

                            {/* Pagination */}
                            <div className="mt-8 flex flex-col sm:flex-row items-center justify-between">
                                <div className="flex items-center space-x-2 mb-4 sm:mb-0">
                                    <span className="text-sm text-gray-700">
                                        Showing <span className="font-medium">{products.from}</span> to <span className="font-medium">{products.to}</span> of{' '}
                                        <span className="font-medium">{products.total}</span> results
                                    </span>
                                    <select
                                        value={perPage}
                                        onChange={handlePerPageChange}
                                        className="text-sm border-gray-300 rounded-md shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                    >
                                        <option value="10">10 per page</option>
                                        <option value="20">20 per page</option>
                                        <option value="50">50 per page</option>
                                        <option value="100">100 per page</option>
                                    </select>
                                </div>

                                <Pagination
                                    currentPage={products.current_page}
                                    lastPage={products.last_page}
                                    onPageChange={handlePageChange}
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
