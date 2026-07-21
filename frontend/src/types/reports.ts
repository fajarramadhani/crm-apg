export interface ReportFilter {
  date_from?: string;
  date_to?: string;
  division_id?: number;
  application_id?: number;
  category_id?: number;
  priority?: number;
  status?: string;
  pic_id?: number;
}

export interface ReportSummaryResponse {
  data: {
    volume?: any;
    sla?: any;
    quality?: any;
    deployment?: any;
    aging?: any;
  };
  filters: ReportFilter;
  period: {
    date_from: string;
    date_to: string;
  };
}

export interface BlobResponse {
  blob: Blob;
  filename: string;
}
