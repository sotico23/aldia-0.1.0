type ClientConfig = {
    app_name?: string;
    google_maps_api_key?: string | null;
    reverb?: {
        key?: string | null;
        host?: string | null;
        port?: number | string | null;
        scheme?: string | null;
    };
};

type InertiaPageData = {
    props?: {
        client_config?: ClientConfig;
    };
};

function readClientConfig(): ClientConfig {
    if (typeof document === 'undefined') {
        return {};
    }

    const pageData = document.getElementById('app')?.dataset.page;

    if (!pageData) {
        return {};
    }

    const page = JSON.parse(pageData) as InertiaPageData;

    return page.props?.client_config ?? {};
}

export const clientConfig = readClientConfig();
