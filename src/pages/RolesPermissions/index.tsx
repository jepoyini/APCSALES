import React, { useEffect, useMemo, useState } from "react";
import { KeyRound, RotateCcw, Save, ShieldCheck } from "lucide-react";
import {
  Badge,
  Button,
  Card,
  CardBody,
  CardHeader,
  Col,
  Container,
  FormGroup,
  Input,
  Label,
  Row,
} from "reactstrap";
import Swal from "sweetalert2";
import BreadCrumb from "../../Components/Common/BreadCrumb";
import { APIClient } from "../../helpers/api_helper";
import { getAuthUser } from "../../helpers/auth_storage";
import {
  AppPermission,
  AppRole,
  getAvailablePermissions,
  getDefaultRolePermissions,
  getRolePermissionsMap,
  hydrateRolePermissionsMap,
  resetRolePermissionsMap,
  saveRolePermissionsMap,
} from "../../helpers/permissions";

type RoleConfig = Record<AppRole, AppPermission[]>;

const ROLE_LABELS: Record<AppRole, string> = {
  admin: "Admin",
  manager: "Manager",
  staff: "Staff",
};

const ROLE_COLORS: Record<AppRole, string> = {
  admin: "danger",
  manager: "info",
  staff: "success",
};

const ROLE_IDS: Record<AppRole, number> = {
  admin: 1,
  manager: 2,
  staff: 3,
};

const roles: AppRole[] = ["admin", "manager", "staff"];

const groupPermissionLabel = (permission: AppPermission) => {
  const [module, action] = permission.split(".");
  return {
    module: module.replace(/_/g, " ").replace(/\b\w/g, (char) => char.toUpperCase()),
    action: action.replace(/_/g, " ").replace(/\b\w/g, (char) => char.toUpperCase()),
  };
};

const normalizeApiRole = (role: any): AppRole => {
  const roleId = Number(role?.id ?? role?.role_id ?? role?.roleId);
  const roleName = String(role?.name ?? role?.role ?? role?.role_name ?? "")
    .trim()
    .toLowerCase();

  if (roleId === 1 || roleName === "admin") return "admin";
  if (roleId === 2 || roleName === "manager") return "manager";
  return "staff";
};

const RolesPermissionsPage: React.FC = () => {
  document.title = "Manage Roles & Permissions | APC Sales Analytics";

  const apipost = new APIClient();
  const authUser: any = getAuthUser();
  const [rolePermissions, setRolePermissions] = useState<RoleConfig>(getRolePermissionsMap());
  const [roleIds, setRoleIds] = useState<Record<AppRole, number | null>>({
    admin: null,
    manager: null,
    staff: null,
  });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    const syncRolePermissions = () => {
      setRolePermissions(getRolePermissionsMap());
    };

    window.addEventListener("permissions-updated", syncRolePermissions);
    return () => {
      window.removeEventListener("permissions-updated", syncRolePermissions);
    };
  }, []);

  useEffect(() => {
    let mounted = true;

    const fetchRoles = async () => {
      try {
        setLoading(true);
        const response: any = await apipost.post("/roles/list", { uid: authUser?.id });
        const rows = Array.isArray(response?.roles) ? response.roles : [];

        if (rows.length > 0) {
          const nextIds: Record<AppRole, number | null> = {
            admin: null,
            manager: null,
            staff: null,
          };

          rows.forEach((role) => {
            const normalizedRole = normalizeApiRole(role);
            nextIds[normalizedRole] = Number(role?.id) || null;
          });

          const nextPermissions = hydrateRolePermissionsMap(rows);
          if (mounted) {
            setRoleIds(nextIds);
            setRolePermissions(nextPermissions);
          }
        } else if (mounted) {
          setRolePermissions(getRolePermissionsMap());
        }
      } catch (error) {
        console.error("Failed to fetch roles:", error);
        if (mounted) {
          await Swal.fire({
            icon: "error",
            text: "Failed to load roles and permissions from the API.",
            confirmButtonText: "OK",
          });
        }
      } finally {
        if (mounted) {
          setLoading(false);
        }
      }
    };

    fetchRoles();

    return () => {
      mounted = false;
    };
  }, []);

  const groupedPermissions = useMemo(() => {
    const groups = new Map<string, AppPermission[]>();

    getAvailablePermissions().forEach((permission) => {
      const [module] = permission.split(".");
      const current = groups.get(module) || [];
      current.push(permission);
      groups.set(module, current);
    });

    return Array.from(groups.entries()).map(([module, permissions]) => ({
      module,
      label: module.replace(/_/g, " ").replace(/\b\w/g, (char) => char.toUpperCase()),
      permissions,
    }));
  }, []);

  const totals = useMemo(
    () =>
      roles.map((role) => ({
        role,
        total: rolePermissions[role].length,
      })),
    [rolePermissions]
  );

  const togglePermission = (role: AppRole, permission: AppPermission) => {
    setRolePermissions((current) => {
      const granted = current[role].includes(permission);
      const nextPermissions = granted
        ? current[role].filter((item) => item !== permission)
        : [...current[role], permission];

      return {
        ...current,
        [role]: nextPermissions,
      };
    });
  };

  const persistRoles = async (nextPermissions: RoleConfig) => {
    for (const role of roles) {
      await apipost.post("/roles/save", {
        uid: authUser?.id,
        id: roleIds[role] ?? undefined,
        name: ROLE_LABELS[role],
        permissions: nextPermissions[role],
      });
    }
  };

  const handleSave = async () => {
    try {
      setSaving(true);
      await persistRoles(rolePermissions);
      const next = saveRolePermissionsMap(rolePermissions);
      setRolePermissions(next);

      await Swal.fire({
        icon: "success",
        text: "Roles and permissions have been updated.",
        confirmButtonText: "OK",
      });
    } catch (error: any) {
      console.error("Failed to save roles:", error);
      await Swal.fire({
        icon: "error",
        text: typeof error === "string" ? error : "Failed to save roles and permissions.",
        confirmButtonText: "OK",
      });
    } finally {
      setSaving(false);
    }
  };

  const handleReset = async () => {
    const result = await Swal.fire({
      icon: "warning",
      title: "Reset role permissions?",
      text: "This will restore the default permissions for admin, manager, and staff.",
      showCancelButton: true,
      confirmButtonText: "Reset",
      cancelButtonText: "Cancel",
    });

    if (!result.isConfirmed) return;

    try {
      setSaving(true);
      const defaults = resetRolePermissionsMap();
      await persistRoles(defaults);
      setRolePermissions(defaults);

      await Swal.fire({
        icon: "success",
        text: "Default role permissions restored.",
        confirmButtonText: "OK",
      });
    } catch (error: any) {
      console.error("Failed to reset roles:", error);
      await Swal.fire({
        icon: "error",
        text: typeof error === "string" ? error : "Failed to reset role permissions.",
        confirmButtonText: "OK",
      });
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="page-content">
      <Container fluid>
        <BreadCrumb title="Roles & Permissions" pageTitle="Dashboard" />

        <Row className="mb-4 align-items-center">
          <Col md={8}>
            <div className="d-flex align-items-center gap-2 mb-1">
              <ShieldCheck size={22} className="text-primary" />
              <h2 className="mb-0">Manage Roles & Permissions</h2>
            </div>
            <p className="text-muted mb-0">
              Control access to every module and action used in the analytics app
            </p>
          </Col>

          <Col md={4} className="text-md-end mt-3 mt-md-0">
            <div className="d-flex justify-content-md-end gap-2">
              <Button
                color="light"
                className="border d-inline-flex align-items-center gap-2"
                onClick={handleReset}
                disabled={saving || loading}
              >
                <RotateCcw size={14} /> Reset Defaults
              </Button>
              <Button
                color="primary"
                className="d-inline-flex align-items-center gap-2"
                onClick={handleSave}
                disabled={saving || loading}
              >
                <Save size={14} /> {saving ? "Saving..." : "Save Changes"}
              </Button>
            </div>
          </Col>
        </Row>

        <Row className="g-3 mb-4">
          {totals.map(({ role, total }) => (
            <Col md={4} key={role}>
              <Card className="border-0 shadow-sm h-100">
                <CardBody>
                  <div className="d-flex justify-content-between align-items-start mb-3">
                    <Badge color={ROLE_COLORS[role]}>{ROLE_LABELS[role]}</Badge>
                    <KeyRound size={16} className="text-muted" />
                  </div>
                  <h3 className="mb-1">{total}</h3>
                  <p className="text-muted mb-0">
                    {loading ? "Loading permissions..." : "Granted permissions"}
                  </p>
                </CardBody>
              </Card>
            </Col>
          ))}
        </Row>

        <Card className="border-0 shadow-sm">
          <CardHeader className="border-0 pb-0">
            <h6 className="mb-0">Permission Matrix</h6>
          </CardHeader>
          <CardBody>
            <div className="table-responsive">
              <table className="table table-bordered align-middle mb-0">
                <thead>
                  <tr>
                    <th style={{ minWidth: 260 }}>Permission</th>
                    {roles.map((role) => (
                      <th key={role} className="text-center">
                        {ROLE_LABELS[role]}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {groupedPermissions.map((group) => (
                    <React.Fragment key={group.module}>
                      <tr className="table-light">
                        <td colSpan={4} className="fw-semibold">
                          {group.label}
                        </td>
                      </tr>
                      {group.permissions.map((permission) => {
                        const label = groupPermissionLabel(permission);
                        return (
                          <tr key={permission}>
                            <td>
                              <div className="fw-medium">{label.action}</div>
                              <code>{permission}</code>
                            </td>
                            {roles.map((role) => (
                              <td key={`${role}-${permission}`} className="text-center">
                                <FormGroup check className="d-inline-flex justify-content-center">
                                  <Input
                                    type="checkbox"
                                    checked={rolePermissions[role].includes(permission)}
                                    onChange={() => togglePermission(role, permission)}
                                    disabled={saving || loading}
                                  />
                                </FormGroup>
                              </td>
                            ))}
                          </tr>
                        );
                      })}
                    </React.Fragment>
                  ))}
                </tbody>
              </table>
            </div>
          </CardBody>
        </Card>

        <Row className="mt-4">
          <Col xl={8}>
            <Card className="border-0 shadow-sm">
              <CardHeader className="border-0 pb-0">
                <h6 className="mb-0">How It Works</h6>
              </CardHeader>
              <CardBody>
                <div className="text-muted">
                  Changes here update route access, sidebar visibility, and action buttons across
                  all modules. The current role model supports the built-in roles:
                  <Label className="ms-1 mb-0">Admin, Manager, and Staff.</Label>
                </div>
              </CardBody>
            </Card>
          </Col>
          <Col xl={4}>
            <Card className="border-0 shadow-sm">
              <CardHeader className="border-0 pb-0">
                <h6 className="mb-0">Default Roles</h6>
              </CardHeader>
              <CardBody>
                {roles.map((role) => (
                  <div key={role} className="d-flex justify-content-between align-items-center mb-2">
                    <span>{ROLE_LABELS[role]}</span>
                    <Badge color={ROLE_COLORS[role]}>
                      {getDefaultRolePermissions()[role].length} default
                    </Badge>
                  </div>
                ))}
              </CardBody>
            </Card>
          </Col>
        </Row>
      </Container>
    </div>
  );
};

export default RolesPermissionsPage;

