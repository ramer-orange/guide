import * as ec2 from 'aws-cdk-lib/aws-ec2';

/**
 * 環境（production / staging）ごとに変わる値をまとめる。
 *
 * リソース名はすべて `guide-<name>-…` の形で組み立てるため、同じアカウントに
 * 両方の環境を並べて立てられる。合成時の context ではなくコードに置くのは、
 * 付け忘れた context でリソースが消える事故（enableSlackAlerts で起きた種類）を
 * 冗長度やバックアップ保持のような設定で起こさないため。
 */
export type GuideEnvironmentName = 'production' | 'staging';

export interface GuideEnvironmentConfig {
  readonly name: GuideEnvironmentName;
  /**
   * GitHub の Environment 名。OIDC の sub クレーム
   * （repo:<owner>/<repo>:environment:<この値>）と一致したジョブだけがデプロイ用 Role を引き受けられる。
   */
  readonly githubEnvironment: string;
  readonly vpcCidr: string;
  /**
   * true なら DB・S3・ECR・ログをスタック削除後も残し、DB と ALB に削除保護を掛ける。
   * staging は立てて壊す使い方をするので false にし、同名での作り直しが衝突しないようにする。
   */
  readonly retainData: boolean;
  readonly database: {
    readonly instanceSize: ec2.InstanceSize;
    readonly multiAz: boolean;
    /** RDS 自動バックアップ（PITR）の保持日数。 */
    readonly backupRetentionDays: number;
  };
  /** AWS Backup による長期保管。未指定なら RDS 自動バックアップだけにする。 */
  readonly backup?: {
    readonly dailyRetentionDays: number;
    readonly monthlyRetentionDays: number;
  };
  readonly web: {
    readonly cpu: number;
    readonly memoryMiB: number;
    readonly minTasks: number;
    readonly maxTasks: number;
  };
  /** 外形監視の実行間隔（分）。未指定なら Canary を作らない。 */
  readonly canaryIntervalMinutes?: number;
}

export const environments: Record<GuideEnvironmentName, GuideEnvironmentConfig> =
  {
    production: {
      name: 'production',
      githubEnvironment: 'production',
      vpcCidr: '10.0.0.0/16',
      retainData: true,
      database: {
        // セッション・キャッシュ・キューも DB に置くため、micro（1GB）ではなく small（2GB）にする。
        instanceSize: ec2.InstanceSize.SMALL,
        multiAz: true,
        backupRetentionDays: 7,
      },
      // 他人のデータを預かる前提の保持期間（docs/AWS_RESTORE_RUNBOOK.md）。
      // 直近 7 日は PITR、35 日までは日次、1 年までは月次で戻せる。
      backup: {
        dailyRetentionDays: 35,
        monthlyRetentionDays: 365,
      },
      web: {
        cpu: 512,
        memoryMiB: 1024,
        minTasks: 2,
        // 2 台で捌けない負荷では db.t4g.small 側が先に詰まるため、
        // Web タスクだけを横に伸ばしてもこれ以上は効果が出ない。
        maxTasks: 3,
      },
      // 2 回連続の失敗で通知するので、検知まで約 10 分。
      canaryIntervalMinutes: 5,
    },
    staging: {
      name: 'staging',
      githubEnvironment: 'staging',
      // 将来 VPC ピアリングなどで繋ぐ可能性を残し、production と重ならない範囲にする。
      vpcCidr: '10.1.0.0/16',
      retainData: false,
      database: {
        instanceSize: ec2.InstanceSize.MICRO,
        multiAz: false,
        backupRetentionDays: 1,
      },
      web: {
        cpu: 512,
        memoryMiB: 1024,
        minTasks: 1,
        maxTasks: 1,
      },
      // 利用者がいないので外形監視は置かない。デプロイ時の疎通確認で足りる。
    },
  };

/** `guide-production-…` のようなリソース名の接頭辞。 */
export function resourcePrefix(config: GuideEnvironmentConfig): string {
  return `guide-${config.name}`;
}

/** DB のマスターユーザー（マイグレーション専用）と、アプリが接続に使うユーザー。 */
export const DATABASE_ADMIN_USERNAME = 'guide_admin';
export const DATABASE_APP_USERNAME = 'guide_app';
export const DATABASE_NAME = 'guide';
