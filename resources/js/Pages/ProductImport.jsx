import React, { useState, useCallback, useEffect } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { useDropzone } from 'react-dropzone';
import { router } from '@inertiajs/react';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout';
import { toast } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';

// Set up axios defaults
axios.defaults.withCredentials = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';

export default function ProductImport({ auth }) {
    const { flash } = usePage().props || {};
    
    // Set up toast notifications
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);
    
    // Redirect if not authenticated
    useEffect(() => {
        if (!auth?.user) {
            router.visit(route('login'));
            return;
        }
    }, [auth?.user]);
    
    if (!auth?.user) {
        return (
            <div className="flex items-center justify-center min-h-screen">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500 mx-auto"></div>
                    <p className="mt-4 text-gray-600">Redirecting to login...</p>
                </div>
            </div>
        );
    }
    const [csvFile, setCsvFile] = useState(null);
    const [images, setImages] = useState([]);
    const [uploadProgress, setUploadProgress] = useState({});
    const [isUploading, setIsUploading] = useState(false);
    const [status, setStatus] = useState('');

    // Handle CSV file selection
    const handleCsvChange = (e) => {
        const file = e.target.files[0];
        if (file) {
            if (file.type === 'text/csv' || file.name.endsWith('.csv')) {
                setCsvFile(file);
                setStatus('CSV file selected: ' + file.name);
            } else {
                setStatus('Error: Please upload a valid CSV file');
                toast.error('Please upload a valid CSV file');
            }
        }
    };

    // Handle image drop
    const onDrop = useCallback((acceptedFiles, rejectedFiles) => {
        // Handle rejected files
        if (rejectedFiles.length > 0) {
            setStatus(`Couldn't add ${rejectedFiles.length} file(s). Only images are allowed.`);
            toast.warning(`Couldn't add ${rejectedFiles.length} file(s). Only images are allowed.`);
        }

        // Process accepted files
        const imageFiles = acceptedFiles.filter(file => 
            file.type.startsWith('image/')
        );
        
        // Limit total number of images to 20
        if (images.length + imageFiles.length > 20) {
            setStatus('Maximum 20 images can be uploaded at once');
            toast.warning('Maximum 20 images can be uploaded at once');
            return;
        }
        
        setImages(prevImages => [
            ...prevImages,
            ...imageFiles.map(file => ({
                file,
                preview: URL.createObjectURL(file),
                sku: '',
                status: 'pending',
                progress: 0
            }))
        ]);
        
        setStatus(`Added ${imageFiles.length} image(s). Please enter SKU for each image.`);
    }, [images.length]);

    const { getRootProps, getInputProps, isDragActive } = useDropzone({
        onDrop,
        accept: 'image/*',
        multiple: true
    });

    // Update SKU for an image
    const updateImageSku = (index, sku) => {
        setImages(prevImages => 
            prevImages.map((img, i) => 
                i === index ? { ...img, sku: sku.toUpperCase() } : img
            )
        );
    };

    // Clean up object URLs
    useEffect(() => {
        return () => {
            images.forEach(image => URL.revokeObjectURL(image.preview));
        };
    }, [images]);

    // Upload files in chunks
    const uploadFiles = async () => {
        // Validate inputs
        if (!csvFile) {
            setStatus('Please select a CSV file');
            toast.error('Please select a CSV file');
            return;
        }

        if (images.length === 0) {
            setStatus('Please add at least one image');
            toast.error('Please add at least one image');
            return;
        }

        // Check if all images have SKUs
        const imagesWithoutSku = images.filter(img => !img.sku.trim());
        if (imagesWithoutSku.length > 0) {
            setStatus('Please enter SKU for all images');
            toast.error(`Please enter SKU for ${imagesWithoutSku.length} image(s)`);
            return;
        }

        setIsUploading(true);
        setStatus('Starting upload process...');
        toast.info('Starting upload process...');

        try {
            // First upload CSV
            setStatus('Uploading CSV file...');
            const csvFormData = new FormData();
            csvFormData.append('csv', csvFile);
            
            // Get CSRF token from the meta tag or use the one from the document
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || 
                             document.head.querySelector('meta[name="csrf-token"]')?.content ||
                             '';
            
            const headers = {
                'Content-Type': 'multipart/form-data'
            };
            
            // Only add CSRF token if it exists
            if (csrfToken) {
                headers['X-CSRF-TOKEN'] = csrfToken;
            } else {
                console.warn('CSRF token not found. Make sure you have a meta tag with name="csrf-token" in your HTML head.');
            }
            
            const csvResponse = await axios.post(route('api.products.import.csv'), csvFormData, {
                headers,
                onUploadProgress: (progressEvent) => {
                    const progress = Math.round((progressEvent.loaded * 100) / (progressEvent.total || 1));
                    setStatus(`Uploading CSV: ${progress}%`);
                }
            });

            if (csvResponse.status !== 200) {
                throw new Error(csvResponse.data.message || 'Failed to upload CSV');
            }

            // Then upload images in chunks
            const chunkSize = 3; // Number of images to upload simultaneously
            setStatus('Uploading images...');
            
            for (let i = 0; i < images.length; i += chunkSize) {
                const chunk = images.slice(i, i + chunkSize);
                
                await Promise.all(chunk.map(async (img, idx) => {
                    const currentIndex = i + idx;
                    
                    try {
                        // Update status to uploading
                        setImages(prev => prev.map((item, i) => 
                            i === currentIndex ? { ...item, status: 'uploading', progress: 0 } : item
                        ));

                        const formData = new FormData();
                        formData.append('image', img.file);
                        formData.append('sku', img.sku);
                        
                        await axios.post(route('api.products.upload.image'), formData, {
                            headers: {
                                'Content-Type': 'multipart/form-data',
                                ...(csrfToken && { 'X-CSRF-TOKEN': csrfToken })
                            },
                            onUploadProgress: (progressEvent) => {
                                const progress = Math.round((progressEvent.loaded * 100) / (progressEvent.total || 1));
                                setImages(prev => prev.map((item, i) => 
                                    i === currentIndex ? { ...item, progress } : item
                                ));
                            }
                        });

                        // Update status to completed
                        setImages(prev => prev.map((item, i) => 
                            i === currentIndex ? { ...item, status: 'completed', progress: 100 } : item
                        ));
                        
                        toast.success(`Image ${currentIndex + 1} uploaded successfully`);
                        
                    } catch (error) {
                        console.error('Error uploading image:', error);
                        setImages(prev => prev.map((item, i) => 
                            i === currentIndex ? { 
                                ...item, 
                                status: 'error',
                                error: error.response?.data?.message || 'Upload failed'
                            } : item
                        ));
                        toast.error(`Failed to upload image ${currentIndex + 1}`);
                    }
                }));
            }

            setStatus('Upload completed successfully!');
            toast.success('All files uploaded successfully!');
            
            // Reset form after successful upload
            setCsvFile(null);
            setImages([]);
            setUploadProgress({});
            
        } catch (error) {
            console.error('Upload failed:', error);
            const errorMessage = error.response?.data?.message || 'Upload failed. Please try again.';
            setStatus(`Error: ${errorMessage}`);
            toast.error(errorMessage);
        } finally {
            setIsUploading(false);
        }
    };

    return (
        <AppLayout
            auth={auth}
            header={
                <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                    Bulk Product Import
                </h2>
            }
        >
            <Head title="Bulk Product Import" />
            
            <div className="max-w-4xl mx-auto">
                <div className="text-center mb-8">
                    <h1 className="text-3xl font-bold text-gray-900">Bulk Product Import</h1>
                    <p className="mt-2 text-sm text-gray-600">
                        Upload your CSV file and product images to import multiple products at once
                    </p>
                </div>

                {/* CSV Upload Section */}
                <div className="bg-white shadow rounded-lg p-6 mb-8">
                    <h2 className="text-lg font-medium text-gray-900 mb-4">1. Upload Product CSV</h2>
                    <div className="mt-1 flex items-center">
                        <label className="w-full flex flex-col items-center px-4 py-6 bg-white text-blue-500 rounded-lg shadow-lg tracking-wide uppercase border border-blue-500 cursor-pointer hover:bg-blue-50">
                            <svg className="w-8 h-8" fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path d="M16.88 9.1A4 4 0 0 1 16 17H5a5 5 0 0 1-1-9.9V7a3 3 0 0 1 4.52-2.59A4.98 4.98 0 0 1 17 8c0 .38-.04.74-.12 1.1zM11 11h3l-4-4-4 4h3v3h2v-3z" />
                            </svg>
                            <span className="mt-2 text-base leading-normal">
                                {csvFile ? csvFile.name : 'Select a CSV file'}
                            </span>
                            <input 
                                type="file" 
                                className="hidden" 
                                accept="text/csv,application/csv"
                                onChange={handleCsvChange}
                            />
                        </label>
                    </div>
                    <p className="mt-2 text-sm text-gray-500">
                        Upload a CSV file containing product details with SKU, name, description, etc.
                    </p>
                </div>

                {/* Image Upload Section */}
                <div className="bg-white shadow rounded-lg p-6 mb-8">
                    <h2 className="text-lg font-medium text-gray-900 mb-4">2. Upload Product Images</h2>
                    
                    <div 
                        {...getRootProps()} 
                        className={`border-2 border-dashed rounded-lg p-12 text-center cursor-pointer transition-colors ${
                            isDragActive ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:border-blue-400'
                        }`}
                    >
                        <input {...getInputProps()} />
                        <div className="space-y-2">
                            <svg 
                                className="mx-auto h-12 w-12 text-gray-400" 
                                fill="none" 
                                stroke="currentColor" 
                                viewBox="0 0 24 24" 
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <path 
                                    strokeLinecap="round" 
                                    strokeLinejoin="round" 
                                    strokeWidth="2" 
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                ></path>
                            </svg>
                            <div className="text-sm text-gray-600">
                                {isDragActive ? (
                                    <p>Drop the images here ...</p>
                                ) : (
                                    <p>Drag & drop images here, or click to select files</p>
                                )}
                            </div>
                            <p className="text-xs text-gray-500">
                                PNG, JPG, GIF up to 10MB
                            </p>
                        </div>
                    </div>

                    {/* Image Preview Grid */}
                    {images.length > 0 && (
                        <div className="mt-6">
                            <h3 className="text-md font-medium text-gray-900 mb-3">Selected Images</h3>
                            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                {images.map((img, index) => (
                                    <div key={index} className="border rounded-lg overflow-hidden bg-gray-50">
                                        <div className="relative pb-full">
                                            <img 
                                                src={img.preview} 
                                                alt={`Preview ${index + 1}`}
                                                className="absolute h-full w-full object-cover"
                                            />
                                            <div className="absolute inset-0 bg-black bg-opacity-30 flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity">
                                                <span className="text-white text-sm font-medium">
                                                    {img.status === 'uploading' ? 'Uploading...' : 
                                                     img.status === 'completed' ? '✓ Uploaded' :
                                                     img.status === 'error' ? '✕ Error' : 'Ready'}
                                                </span>
                                            </div>
                                        </div>
                                        <div className="p-2">
                                            <input
                                                type="text"
                                                value={img.sku}
                                                onChange={(e) => updateImageSku(index, e.target.value)}
                                                placeholder="Enter SKU"
                                                className="w-full px-2 py-1 text-sm border rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                disabled={img.status === 'uploading'}
                                            />
                                            {uploadProgress[img.file.name] > 0 && (
                                                <div className="w-full bg-gray-200 rounded-full h-2 mt-2">
                                                    <div 
                                                        className="bg-blue-600 h-2 rounded-full" 
                                                        style={{ width: `${uploadProgress[img.file.name]}%` }}
                                                    ></div>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {/* Status and Actions */}
                <div className="bg-white shadow rounded-lg p-6">
                    <div className="flex justify-between items-center">
                        <div>
                            {status && (
                                <p className={`text-sm ${
                                    status.includes('failed') ? 'text-red-600' : 'text-gray-600'
                                }`}>
                                    {status}
                                </p>
                            )}
                        </div>
                        <button
                            onClick={uploadFiles}
                            disabled={isUploading || !csvFile || images.length === 0}
                            className={`px-6 py-2 border border-transparent rounded-md shadow-sm text-base font-medium text-white ${
                                isUploading || !csvFile || images.length === 0
                                    ? 'bg-gray-400 cursor-not-allowed'
                                    : 'bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500'
                            }`}
                        >
                            {isUploading ? 'Uploading...' : 'Start Import'}
                        </button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
