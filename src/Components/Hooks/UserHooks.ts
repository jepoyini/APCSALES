import { useEffect, useMemo, useState } from "react";
import { APIClient, getLoggedinUser } from "../../helpers/api_helper";
import {
  hasPermission as checkPermission,
  hydrateRolePermissionsMap,
  normalizeUserRole,
  resolveUserPermissions,
} from "../../helpers/permissions";

const useProfile = () => {
  const [loading, setLoading] = useState(true);
  const [userProfile, setUserProfile] = useState<any>(null);

  useEffect(() => {
    let mounted = true;

    const loadProfile = async () => {
      const userProfileSession = getLoggedinUser();
      if (!mounted) return;

      setUserProfile(userProfileSession ? userProfileSession : null);

      if (!userProfileSession?.id) {
        setLoading(false);
        return;
      }

      try {
        const api = new APIClient();
        const response: any = await api.post("/roles/list", { uid: userProfileSession.id });
        if (Array.isArray(response?.roles)) {
          hydrateRolePermissionsMap(response.roles);
          setUserProfile(getLoggedinUser());
        }
      } catch (error) {
        console.error("Failed to load role permissions:", error);
      } finally {
        if (mounted) {
          setLoading(false);
        }
      }
    };

    loadProfile();

    return () => {
      mounted = false;
    };
  }, []);

  const token = userProfile && (userProfile["token"] || userProfile["csrf_token"]);

  return { userProfile, loading, token };
};

const usePermissions = () => {
  const { userProfile, loading, token } = useProfile();
  const [permissionVersion, setPermissionVersion] = useState(0);

  useEffect(() => {
    const handlePermissionUpdate = () => {
      setPermissionVersion((current) => current + 1);
    };

    window.addEventListener("permissions-updated", handlePermissionUpdate);
    window.addEventListener("storage", handlePermissionUpdate);

    return () => {
      window.removeEventListener("permissions-updated", handlePermissionUpdate);
      window.removeEventListener("storage", handlePermissionUpdate);
    };
  }, []);

  const permissions = useMemo(
    () => resolveUserPermissions(userProfile),
    [permissionVersion, userProfile]
  );

  const role = useMemo(() => normalizeUserRole(userProfile), [permissionVersion, userProfile]);

  const hasPermission = (required?: string | string[]) =>
    checkPermission(userProfile, required);

  return { userProfile, loading, token, permissions, role, hasPermission };
};

export { useProfile, usePermissions };
