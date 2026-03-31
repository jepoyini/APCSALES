import React, { useMemo, useState } from "react";
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
import {
  AppPermission,
  AppRole,
  getAvailablePermissions,
  getDefaultRolePermissions,
  getRolePermissionsMap,
  saveRolePermissionsMap,
  resetRolePermissionsMap,
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

const roles: AppRole[] = ["admin", "manager", "staff"];

const groupPermissionLabel = (permission: AppPermission) => {
  const [module, action] = permission.split(".");
  return {
    module: module.replace(/_/g, " ").replace(/\b\w/g, (char) => char.toUpperCase()),
    action: action.replace(/_/g, " ").replace(/\b\w/g, (char) => char.toUpperCase()),
  };
};

const RolesPermissionsPage: React.FC = () => {
  document.title = "Manage Roles & Permissions | APC Sales Analytics";

  const [rolePermissions, setRolePermissions] = useState<RoleConfig>(getRolePermissionsMap());
  const [saving, setSaving] = useState(false);

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

  const handleSave = async () => {
    try {
      setSaving(true);
      const next = saveRolePermissionsMap(rolePermissions);
      setRolePermissions(next);

      await Swal.fire({
        icon: "success",
        text: "Roles and permissions have been updated.",
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

    const defaults = resetRolePermissionsMap();
    setRolePermissions(defaults);

    await Swal.fire({
      icon: "success",
      text: "Default role permissions restored.",
      confirmButtonText: "OK",
    });
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
                disabled={saving}
              >
                <RotateCcw size={14} /> Reset Defaults
              </Button>
              <Button
                color="primary"
                className="d-inline-flex align-items-center gap-2"
                onClick={handleSave}
                disabled={saving}
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
                  <p className="text-muted mb-0">Granted permissions</p>
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
