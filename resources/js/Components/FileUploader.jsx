import React, { useState, useRef } from 'react';
import { useForm } from '@inertiajs/react';
import PrimaryButton from '@/Components/PrimaryButton';
import InputError from '@/Components/InputError';
import { toast } from 'react-toastify';

// Generate a UUIDv4 (used as upload session ID)
const generateUUID = () => {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
        const r = Math.random() * 16 | 0;
        const v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
};

// Generate an MD5 hash of a chunk or file (must match backend's PHP md5)
const calculateMD5 = async (chunk) => {
    const buffer = await (chunk instanceof Blob ? chunk.arrayBuffer() : chunk);
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.length; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    const CryptoJS = await import('crypto-js');
    return CryptoJS.MD5(CryptoJS.enc.Latin1.parse(binary)).toString();
};

// Chunk size used for splitting large files (5MB)
const CHUNK_SIZE = 5 * 1024 * 1024;

// Get CSRF token from meta tag
const getCsrfToken = () => {
    return document.head.querySelector('meta[name="csrf-token"]')?.content;
};

// 🕐 Poll the backend to wait until image processing finishes
const waitForImageReady = async (uploadId, timeout = 300000 /* 5 minutes */, interval = 2000) => {
    const startTime = Date.now();
    let lastStatus = '';

    while (Date.now() - startTime < timeout) {
        try {
            const response = await fetch(`/api/upload/${uploadId}/status`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                const error = await response.json().catch(() => ({}));
                throw new Error(error.message || 'Failed to check image status');
            }

            const result = await response.json();
            console.log('Image processing status:', result.status);

            if (result.status === 'complete') {
                return true; // Image is ready to attach
            }

            // Update last status and log if it changed
            if (result.status && result.status !== lastStatus) {
                lastStatus = result.status;
                console.log(`Processing status: ${result.status}`);
                toast.info(`Processing: ${result.status.replace('_', ' ')}`);
            }

        } catch (error) {
            console.error('Error during polling:', error);
            // Don't fail immediately on network errors, only throw if we're out of time
            if (Date.now() - startTime + 10000 >= timeout) {
                throw error;
            }
        }

        // Wait before checking again
        await new Promise(resolve => setTimeout(resolve, interval));
    }

    throw new Error('Image processing took too long. Please try again or use a smaller image.');
};

export default function FileUploader({ onUploadComplete, productSku = '' }) {
    const [isUploading, setIsUploading] = useState(false);
    const [progress, setProgress] = useState(0);
    const [uploadId, setUploadId] = useState(null);
    const [fileName, setFileName] = useState('');
    const fileInputRef = useRef(null);
    const [sku, setSku] = useState(productSku);

    // Form state using Inertia's useForm hook
    const { data, setData, errors } = useForm({
        file: null,
        sku: productSku,
        is_primary: false,
    });

    // Handle file selection
    const handleFileChange = (e) => {
        const file = e.target.files[0];
        if (!file) return;
        setFileName(file.name);
        setData('file', file);
        setUploadId(null); // Reset previous upload session if any
    };

    // Upload a single chunk to the backend
    const uploadChunk = async (chunk, chunkIndex, totalChunks, uploadId = null) => {
        const currentUploadId = uploadId || generateUUID();
        const chunkChecksum = await calculateMD5(chunk);

        const formData = new FormData();
        formData.append('chunk', chunk, fileName);
        formData.append('upload_id', currentUploadId);
        formData.append('chunk_index', chunkIndex.toString());
        formData.append('total_chunks', totalChunks.toString());
        formData.append('chunk_checksum', chunkChecksum);
        formData.append('file_name', fileName);
        formData.append('file_size', chunk.size.toString());
        formData.append('mime_type', chunk.type || 'application/octet-stream');

        const response = await fetch('/api/upload/chunk', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: formData,
        });

        if (!response.ok) throw new Error('Chunk upload failed');
        return await response.json();
    };

    // After all chunks are uploaded, tell backend to assemble the file
    const completeUpload = async (uploadId, checksum) => {
        const formData = new FormData();
        formData.append('upload_id', uploadId);
        formData.append('file_checksum', checksum);
        formData.append('_token', getCsrfToken());

        const response = await fetch('/api/upload/complete', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
            body: formData,
        });

        if (!response.ok) throw new Error('Upload completion failed');
        console.log(" << === response === >>",response);
        return await response.json();
    };

    // Attach uploaded image to a product using SKU with retry logic
    const attachToProduct = async (uploadId, sku, isPrimary = false, retryCount = 0, maxRetries = 10) => {
        try {
            console.log(`Attempting to attach product (attempt ${retryCount + 1}/${maxRetries})`);
            
            const response = await fetch('/api/upload/attach-to-product', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    upload_id: uploadId,
                    sku: sku,
                    is_primary: isPrimary,
                }),
            });

            // Handle processing status (202)
            if (response.status === 202) {
                const data = await response.json().catch(() => ({}));
                const processingTime = data.processing_time || 0;
                
                if (retryCount >= maxRetries) {
                    throw new Error('Image processing is taking too long. Please try again later.');
                }
                
                // Show a toast notification for processing status
                if (retryCount === 0) {
                    toast.info('🔄 Processing your image. Please wait...', {
                        autoClose: false,
                        closeOnClick: false,
                        draggable: false,
                    });
                }
                
                // Calculate delay with exponential backoff (max 10 seconds)
                const baseDelay = 1000; // 1 second
                const maxDelay = 10000; // 10 seconds
                const delay = Math.min(
                    baseDelay * Math.pow(2, retryCount) + Math.random() * 1000, // Add some jitter
                    maxDelay
                );
                
                console.log(`Processing not complete, retrying in ${Math.round(delay/1000)}s...`);
                
                await new Promise(resolve => setTimeout(resolve, delay));
                
                // Retry the attachment
                return attachToProduct(uploadId, sku, isPrimary, retryCount + 1, maxRetries);
            }

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                console.error('Attach to product error:', errorData);
                throw new Error(errorData.message || 'Failed to attach image to product');
            }

            // Clear any existing processing toast
            toast.dismiss();
            return await response.json();
            
        } catch (error) {
            console.error('Error attaching to product:', error);
            // Clear any existing processing toast
            toast.dismiss();
            throw error;
        }
    };

    // Main form submission logic
    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!data.file) return;

        setIsUploading(true);
        setProgress(0);

        try {
            const file = data.file;
            const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
            let currentUploadId = uploadId || generateUUID();
            const fileChecksum = await calculateMD5(file);

            setUploadId(currentUploadId); // Save for resume or reference

            // Step 1: Upload all chunks sequentially
            for (let i = 0; i < totalChunks; i++) {
                const start = i * CHUNK_SIZE;
                const end = Math.min(file.size, start + CHUNK_SIZE);
                const chunk = file.slice(start, end, file.type);

                await uploadChunk(chunk, i, totalChunks, currentUploadId);

                // Update progress bar
                setProgress(Math.round(((i + 1) / totalChunks) * 100));
                await new Promise(resolve => setTimeout(resolve, 100)); // throttle
            }

            // Step 2: Signal server to complete the file assembly
            await completeUpload(currentUploadId, fileChecksum);

            // Step 3: Wait for image processing to complete before attaching
            await waitForImageReady(currentUploadId);

            // Step 4: Attach to product (if SKU is given)
            if (sku) {
                try {
                    await attachToProduct(currentUploadId, sku, data.is_primary);
                    uploadSuccess = true;
                    toast.success('✅ File uploaded and attached to product!');
                } catch (error) {
                    // If attachment fails but upload was successful, show a warning
                    if (error.message.includes('processing') || error.message.includes('try again')) {
                        toast.warning(`⚠️ ${error.message}`);
                    } else {
                        toast.error(`❌ ${error.message}`);
                    }
                    throw error; // Re-throw to be caught by the outer catch
                }
            } else {
                uploadSuccess = true;
                toast.success('✅ File uploaded successfully!');
            }

            if (onUploadComplete && uploadSuccess) {
                onUploadComplete(currentUploadId);
            }

        } catch (error) {
            console.error('❌ Upload error:', error);
            toast.error(error.message || 'Upload failed. Please try again.');
        } finally {
            setIsUploading(false);
        }
    };

    return (
        <div className="space-y-4">
            <form onSubmit={handleSubmit} className="space-y-4">
                {/* File Picker */}
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Upload File
                    </label>
                    <input
                        type="file"
                        ref={fileInputRef}
                        onChange={handleFileChange}
                        disabled={isUploading}
                        className="block w-full text-sm text-gray-500
                                  file:mr-4 file:py-2 file:px-4
                                  file:rounded-md file:border-0
                                  file:text-sm file:font-semibold
                                  file:bg-blue-50 file:text-blue-700
                                  hover:file:bg-blue-100"
                    />
                    <InputError message={errors.file} className="mt-1" />
                </div>

                {/* SKU Field (optional if not passed as prop) */}
                {!productSku && (
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Product SKU (optional)
                        </label>
                        <input
                            type="text"
                            value={sku}
                            onChange={(e) => setSku(e.target.value)}
                            disabled={isUploading}
                            placeholder="Enter product SKU to attach this file"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                        />
                    </div>
                )}

                {/* Primary checkbox */}
                <div className="flex items-center">
                    <input
                        id="is_primary"
                        name="is_primary"
                        type="checkbox"
                        checked={data.is_primary}
                        onChange={(e) => setData('is_primary', e.target.checked)}
                        disabled={isUploading || !sku}
                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    />
                    <label htmlFor="is_primary" className="ml-2 block text-sm text-gray-700">
                        Set as primary image
                    </label>
                </div>

                {/* Progress bar */}
                {isUploading && (
                    <div className="w-full bg-gray-200 rounded-full h-2.5">
                        <div
                            className="bg-blue-600 h-2.5 rounded-full"
                            style={{ width: `${progress}%` }}
                        ></div>
                    </div>
                )}

                {/* Upload button */}
                <div className="flex justify-end">
                    <PrimaryButton
                        type="submit"
                        disabled={isUploading || !data.file}
                        className="disabled:opacity-50"
                    >
                        {isUploading ? 'Uploading...' : 'Upload File'}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    );
}
