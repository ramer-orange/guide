import nextVitals from "eslint-config-next/core-web-vitals";
import nextTypescript from "eslint-config-next/typescript";

const eslintConfig = [
    ...nextVitals,
    ...nextTypescript,
    { ignores: [".next/**", "node_modules/**", "src/types/api.generated.ts"] },
    {
        files: ["src/components/**/*.{ts,tsx}", "src/lib/**/*.{ts,tsx}"],
        rules: {
            "no-restricted-imports": [
                "error",
                {
                    patterns: [
                        {
                            group: [
                                "@/features/*",
                                "@/features/**",
                                "@/app/*",
                                "@/app/**",
                            ],
                            message:
                                "Shared components and libraries cannot import app routes or feature internals.",
                        },
                    ],
                },
            ],
        },
    },
    {
        files: ["src/features/**/*.{ts,tsx}"],
        rules: {
            "no-restricted-imports": [
                "error",
                {
                    patterns: [
                        {
                            group: ["@/app/*", "@/app/**"],
                            message:
                                "Feature modules cannot import app routes.",
                        },
                    ],
                },
            ],
        },
    },
];
export default eslintConfig;
