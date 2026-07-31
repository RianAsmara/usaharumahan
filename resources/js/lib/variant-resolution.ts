export type Variant = {
    id: string;
    price: number;
    salePrice: number | null;
    optionValueIds: string[];
    stock: number;
};

export function findMatchingVariant(
    variants: Variant[],
    selected: Record<string, string>,
    optionIds: string[],
): Variant | null {
    const selectedValueIds = optionIds.map((optionId) => selected[optionId]);

    if (selectedValueIds.some((valueId) => valueId === undefined)) {
        return null;
    }

    return (
        variants.find((variant) =>
            selectedValueIds.every((valueId) =>
                variant.optionValueIds.includes(valueId),
            ),
        ) ?? null
    );
}

export function isValueAvailable(
    variants: Variant[],
    optionId: string,
    valueId: string,
    selected: Record<string, string>,
): boolean {
    const otherSelections = Object.entries(selected).filter(
        ([id]) => id !== optionId,
    );

    return variants.some((variant) => {
        if (!variant.optionValueIds.includes(valueId)) {
            return false;
        }

        if (variant.stock <= 0) {
            return false;
        }

        return otherSelections.every(([, otherValueId]) =>
            variant.optionValueIds.includes(otherValueId),
        );
    });
}
