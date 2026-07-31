import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { ProductCard, type StorefrontProduct } from './product-card';

const baseProduct: StorefrontProduct = {
    id: '1',
    slug: 'keripik-singkong',
    name: 'Keripik Singkong',
    imageUrl: null,
    price: { amount: 15000, isFrom: false },
    inStock: true,
};

describe('ProductCard', () => {
    it('renders the product name and formatted price', () => {
        render(<ProductCard product={baseProduct} />);

        expect(screen.getByText('Keripik Singkong')).toBeInTheDocument();
        expect(screen.getByText('Rp 15.000')).toBeInTheDocument();
    });

    it('shows a "Mulai dari" label when the price is a range', () => {
        render(<ProductCard product={{ ...baseProduct, price: { amount: 15000, isFrom: true } }} />);

        expect(screen.getByText('Mulai dari')).toBeInTheDocument();
    });

    it('does not show "Mulai dari" for a flat price', () => {
        render(<ProductCard product={baseProduct} />);

        expect(screen.queryByText('Mulai dari')).not.toBeInTheDocument();
    });

    it('shows a "Stok habis" badge when out of stock', () => {
        render(<ProductCard product={{ ...baseProduct, inStock: false }} />);

        expect(screen.getByText('Stok habis')).toBeInTheDocument();
    });

    it('links to the product detail page', () => {
        render(<ProductCard product={baseProduct} />);

        expect(screen.getByRole('link')).toHaveAttribute('href', '/produk/keripik-singkong');
    });
});
