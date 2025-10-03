import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import FileUploader from '@/Components/FileUploader';

export default function Uploads() {
    return (
        <AppLayout>
            <Head title="File Uploader" />
            
            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 bg-white border-b border-gray-200">
                            <h1 className="text-2xl font-semibold text-gray-900 mb-6">File Uploader</h1>
                            <div className="max-w-3xl mx-auto">
                                <FileUploader 
                                    onUploadComplete={(result) => {
                                        console.log('Upload completed:', result);
                                    }}
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
