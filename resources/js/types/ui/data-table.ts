export type DataTableColumnAlign = 'left' | 'right' | 'center';

export type DataTableColumn<R> = {
    key: string;
    label: string;
    align?: DataTableColumnAlign;
    width?: string;
    mono?: boolean;
    accessor?: (row: R) => unknown;
};
