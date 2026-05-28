export type EntityId = string | number;

export type ApiEnvelope<T> = {
  data: T;
  message?: string;
  status?: string;
};
