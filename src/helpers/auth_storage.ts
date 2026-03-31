const AUTH_USER_KEY = "authUser";

const resolveStorage = (): Storage => {
  const storageType = (process.env.REACT_APP_AUTH_STORAGE || "session").toLowerCase();
  return storageType === "local" ? localStorage : sessionStorage;
};

export const getAuthStorageType = (): "local" | "session" => {
  const storageType = (process.env.REACT_APP_AUTH_STORAGE || "session").toLowerCase();
  return storageType === "local" ? "local" : "session";
};

export const getAuthUser = <T = any>(): T | null => {
  const value = resolveStorage().getItem(AUTH_USER_KEY);
  if (!value) return null;

  try {
    return JSON.parse(value) as T;
  } catch {
    return null;
  }
};

export const setAuthUser = (value: unknown): void => {
  resolveStorage().setItem(AUTH_USER_KEY, JSON.stringify(value));
};

export const clearAuthUser = (): void => {
  resolveStorage().removeItem(AUTH_USER_KEY);
};
