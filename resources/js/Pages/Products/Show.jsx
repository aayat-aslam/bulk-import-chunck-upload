import React, { useState, useMemo } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

export default function ProductShow({ product }) {
    // Flatten the images array and add variants
    const allImages = useMemo(() => {
        return product.images.map(img => {
            const variants = img.variants || {};
            return {
                ...img,
                variants: variants,
                // Use the largest available variant by default
                mainVariant: variants['1024'] ||
                           variants['512'] ||
                           variants['256'] ||
                           variants['original'] ||
                           { url: img.path || '', width: 0, height: 0 }
            };
        });
    }, [product.images]);

    const [selectedImageIndex, setSelectedImageIndex] = useState(0);
    const [selectedVariant, setSelectedVariant] = useState('1024');

    const currentImage = allImages[selectedImageIndex];
    const currentVariant = currentImage?.variants[selectedVariant] || currentImage?.mainVariant;
    // currentVariant.url = currentVariant?.url.replace(/\/original$/, '_orig');

    return (
        <AppLayout>
            <Head title={product.name} />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 bg-white">
                            <Link
                                href="/products"
                                className="inline-flex items-center text-sm text-gray-600 hover:text-gray-900 mb-6"
                            >
                                <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Back to Products
                            </Link>

                            <div className="md:flex gap-8">
                                {/* Image Gallery */}
                                <div className="md:w-1/2">
                                   {/* Main Image */}
                            <div className="w-full md:w-2/3">
                                {currentImage && currentVariant ? (
                                    <div className="relative">
                                        <img
                                            src={currentVariant.url}
                                            alt={product.name}
                                            className="w-full h-auto rounded-lg shadow-lg"
                                            style={{
                                                maxHeight: '70vh',
                                                objectFit: 'contain'
                                            }}
                                        />

                                        {/* Variant selector */}
                                        <div className="mt-4 flex flex-wrap gap-2">
                                            {Object.entries(currentImage.variants).map(([variant, data]) => (
                                                <button
                                                    key={variant}
                                                    onClick={() => setSelectedVariant(variant)}
                                                    className={`px-3 py-1 text-xs rounded-md ${
                                                        selectedVariant === variant
                                                            ? 'bg-blue-600 text-white'
                                                            : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                                                    }`}
                                                    title={`${variant} (${data.width}×${data.height})`}
                                                >
                                                    {variant}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                ) : (
                                    <div className="w-full h-64 bg-gray-200 rounded-lg flex items-center justify-center">
                                        <span className="text-gray-500">No image available</span>
                                    </div>
                                )}
                            </div>                   </div>
                                    {/* Thumbnails */}
                            <div className="w-full md:w-1/3 mt-4 md:mt-0 md:pl-4">
                                <div className="grid grid-cols-4 gap-2 md:grid-cols-1 max-h-[70vh] overflow-y-auto">
                                    {allImages.map((image, index) => {
                                        const variant = image.variants['256'] || image.variants['512'] || image.mainVariant;
                                        return (
                                            <button
                                                key={index}
                                                onClick={() => {
                                                    setSelectedImageIndex(index);
                                                    // Reset to the largest available variant when changing images
                                                    const availableVariants = Object.keys(image.variants);
                                                    if (availableVariants.includes('1024')) {
                                                        setSelectedVariant('1024');
                                                    } else if (availableVariants.includes('512')) {
                                                        setSelectedVariant('512');
                                                    } else if (availableVariants.includes('256')) {
                                                        setSelectedVariant('256');
                                                    } else if (availableVariants.includes('original')) {
                                                        setSelectedVariant('original');
                                                    }
                                                }}
                                                className={`relative rounded-md overflow-hidden border-2 ${
                                                    selectedImageIndex === index
                                                        ? 'border-blue-500'
                                                        : 'border-transparent hover:border-gray-300'
                                                }`}
                                            >
                                                <img
                                                    src={variant.url}
                                                    alt={`${product.name} - ${index + 1}`}
                                                    className="w-full h-20 object-cover"
                                                />
                                                <div className="absolute bottom-0 left-0 right-0 bg-black bg-opacity-50 text-white text-xs p-1 text-center">
                                                    {Object.keys(image.variants).join(', ')}
                                                </div>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                                {/* Product Info */}
                                <div className="md:w-1/2 mt-6 md:mt-0">
                                    <h1 className="text-3xl font-bold text-gray-900 mb-2">{product.name}</h1>
                                    <p className="text-2xl font-semibold text-gray-800 mb-4">${product.price}</p>

                                    <div className="mb-6">
                                        <h2 className="text-lg font-semibold mb-2">Description</h2>
                                        <p className="text-gray-600">
                                            {product.description || 'No description available.'}
                                        </p>
                                    </div>

                                    <div className="mb-6">
                                        <h2 className="text-lg font-semibold mb-2">Product Details</h2>
                                        <ul className="space-y-2 text-sm text-gray-600">
                                            <li><span className="font-medium">SKU:</span> {product.sku}</li>
                                            <li><span className="font-medium">Added on:</span> {new Date(product.created_at).toLocaleDateString()}</li>
                                            <li><span className="font-medium">Total Images:</span> {product.images.length}</li>
                                        </ul>
                                    </div>

                                    <div className="flex space-x-4 mt-8">
                                        <button className="px-6 py-3 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                            Add to Cart
                                        </button>
                                        <button className="px-6 py-3 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                            Add to Wishlist
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
