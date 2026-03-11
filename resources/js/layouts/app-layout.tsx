import ErrorBoundary from '@/components/error-boundary';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import type { AppLayoutProps } from '@/types';

export default ({ children, breadcrumbs, ...props }: AppLayoutProps) => (
    <AppLayoutTemplate breadcrumbs={breadcrumbs} {...props}>
        <ErrorBoundary>{children}</ErrorBoundary>
    </AppLayoutTemplate>
);
