export type ButtonVariant =
    | 'primary'
    | 'secondary'
    | 'ghost'
    | 'destructive-soft'
    | 'destructive';

export type ButtonSize = 'md' | 'sm' | 'icon';

export type BadgeTone = 'slate' | 'blue' | 'emerald' | 'amber' | 'rose';

export type StatusTone =
    | 'slate'
    | 'blue'
    | 'emerald'
    | 'amber'
    | 'rose';

export type CompanyColor =
    | 'indigo'
    | 'emerald'
    | 'amber'
    | 'rose'
    | 'violet'
    | 'teal'
    | 'orange'
    | 'cyan';

export type DataTableColumnAlign = 'left' | 'right' | 'center';

export type DataTableColumn<R> = {
    key: string;
    label: string;
    align?: DataTableColumnAlign;
    width?: string;
    mono?: boolean;
    accessor?: (row: R) => unknown;
};
