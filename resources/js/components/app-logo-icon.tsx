import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 42" xmlns="http://www.w3.org/2000/svg">
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M6 1H34C36.76 1 39 3.24 39 6V22C39 31 29 38 20 42C11 38 1 31 1 22V6C1 3.24 3.24 1 6 1ZM20 26C16 22 9 17 9 12C9 9 11.5 7 14.5 7C17 7 19 9 20 11C21 9 23 7 25.5 7C28.5 7 31 9 31 12C31 17 24 22 20 26Z"
            />
        </svg>
    );
}
