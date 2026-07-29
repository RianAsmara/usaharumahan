import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { Button } from './button';

describe('Button', () => {
    it('renders its children', () => {
        render(<Button>Simpan</Button>);

        expect(screen.getByRole('button', { name: 'Simpan' })).toBeInTheDocument();
    });

    it('calls onClick when pressed', async () => {
        const onClick = vi.fn();
        const user = userEvent.setup();

        render(<Button onClick={onClick}>Simpan</Button>);
        await user.click(screen.getByRole('button', { name: 'Simpan' }));

        expect(onClick).toHaveBeenCalledOnce();
    });

    it('does not fire onClick when disabled', async () => {
        const onClick = vi.fn();
        const user = userEvent.setup();

        render(
            <Button disabled onClick={onClick}>
                Simpan
            </Button>,
        );
        await user.click(screen.getByRole('button', { name: 'Simpan' }));

        expect(onClick).not.toHaveBeenCalled();
    });
});
