export async function uploadImageDirect(file) {
    const formData = new FormData();
    formData.append('file', file);

    try {
        const response = await fetch('https://frohubecomm.mystagingwebsite.com/wp-json/frohub/v1/upload-comment-image', {
            method: 'POST',
            headers: {
                'Authorization': 'Basic YOUR_BASIC_AUTH_HERE',  // Optional: If required
            },
            body: formData
        });

        const data = await response.json();

        if (data.success && data.url) {
            return data.url;
        } else {
            throw new Error(data.error || 'Upload failed');
        }
    } catch (error) {
        console.error('Upload error:', error);
        throw error;
    }
}
