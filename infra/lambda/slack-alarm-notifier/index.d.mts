// index.mjs は依存パッケージを持たない素の ES Module なので、型だけをここで宣言する。
// TypeScript のテストから handler を読み込むためだけに存在し、Lambda の実行には使わない。
export declare const SEND_TIMEOUT_MS: number;
export declare function handler(event: unknown): Promise<void>;
