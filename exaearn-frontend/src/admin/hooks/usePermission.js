import { useMemo } from "react";
import { useAdminStore } from "../store/useAdminStore";

export function usePermission() {
  const role = useAdminStore((state) => state.role);
  const permissionsByRole = useAdminStore((state) => state.permissionsByRole);

  return useMemo(() => {
    const granted = permissionsByRole[role] ?? [];
    const isSuper = granted.includes("*");

    return {
      role,
      can(permission) {
        return isSuper || granted.includes(permission);
      },
      canAny(permissions = []) {
        return permissions.some((permission) => isSuper || granted.includes(permission));
      },
    };
  }, [permissionsByRole, role]);
}
