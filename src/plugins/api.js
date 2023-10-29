export const apiPlugin = {
    install(app) {
        app.config.globalProperties.$api = async (method, endpoint, data) => {
            // Validate arguments
            if (typeof method !== 'string' || !['GET', 'POST'].includes(method)) {
                throw new Error('Invalid method for API call, must be one of: GET, POST');
            }
            if (typeof endpoint !== 'string') {
                throw new Error('Endpoint must be provided as a string');
            }

            // Allow for API URL override
            let fullUrl = 'api.php';
            console.log(import.meta)
            if (import.meta.env.VITE_APP_INSTALL_URL) {
                const baseUrl = import.meta.env.VITE_APP_INSTALL_URL;

                if (!fullUrl.endsWith('/')) {
                    fullUrl = `${baseUrl}/${fullUrl}`;
                } else {
                    fullUrl = `${baseUrl}${fullUrl}`;
                }
            }

            // Format provided data for either GET or POST
            let postBody = null;

            if (method === 'GET') {
                fullUrl = `${fullUrl}?endpoint=${endpoint}`;

                if (data && !Array.isArray(data)) {
                    throw new Error('Data must be provided as an array');
                }

                if (data && data.length) {
                    const dataUrl = data.join('&');
                    fullUrl = `${fullUrl}&${dataUrl}`;
                }
            } else {
                if (data && typeof data !== 'object') {
                    throw new Error('Data must be provided as an object');
                }

                data.endpoint = endpoint;
                postBody = JSON.stringify(data);
            }

            try {
                const response = await fetch(fullUrl, {
                    method,
                    body: postBody,
                });

                if (!response.ok) {
                    const jsonData = await response.json();
                    return {
                        success: false,
                        error: jsonData.error,
                        data: jsonData.data || null,
                    };
                } else {
                    const jsonData = await response.json();
                    if (jsonData.success) {
                        return {
                            success: true,
                            data: jsonData.data || null,
                        };
                    } else {
                        return {
                            success: false,
                            error: jsonData.error,
                            data: jsonData.data || null,
                        };
                    }
                }
            } catch (error) {
                throw new Error('An unknown AJAX error occurred.');
            }
        };
    },
};
