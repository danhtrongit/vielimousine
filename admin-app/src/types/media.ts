export interface MediaSize {
  url: string;
  width: number;
  height: number;
}

export interface MediaUsedRef {
  type: 'hotel' | 'room';
  id: number;
  name: string;
}

export interface MediaItem {
  id: number;
  title: string;
  alt: string;
  caption: string;
  mime: string;
  filesize: number;
  width: number;
  height: number;
  url: string;
  sizes: {
    thumbnail?: MediaSize;
    medium?: MediaSize;
    large?: MediaSize;
    full?: MediaSize;
  };
  created_at: string;
  used_in?: MediaUsedRef[];
}
