type ShippingProduct = {
    envio_gratis?: boolean;
    envio_gratis_comuna?: string | null;
    precio_envio?: number;
};

const normalize = (value: string) =>
    value.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase()
        .replace(/[_\s]+/g, ' ').trim();

export function marketplaceShipping(products: ShippingProduct[], commune: string): number {
    const destination = normalize(commune);
    return products.reduce((fee, product) => {
        const zone = normalize(product.envio_gratis_comuna ?? '');
        const applies = product.envio_gratis && (!zone || zone === destination);
        return Math.max(fee, applies ? 0 : Number(product.precio_envio ?? 0));
    }, 0);
}
