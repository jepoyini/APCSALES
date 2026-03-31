import React, { useEffect, useMemo, useState } from "react";
import { Edit, Eye, EyeOff, Plus, Search, Shield, UserCog } from "lucide-react";
import { Link } from "react-router-dom";
import {
  Badge,
  Button,
  Card,
  CardBody,
  CardHeader,
  Col,
  Container,
  Input,
  InputGroup,
  InputGroupText,
  Label,
  Modal,
  ModalBody,
  ModalHeader,
  Nav,
  NavItem,
  NavLink,
  Row,
  Spinner,
  TabContent,
  TabPane,
} from "reactstrap";
import { ToastContainer, toast } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import BreadCrumb from "../../Components/Common/BreadCrumb";
import { usePermissions } from "../../Components/Hooks/UserHooks";
import {
  APP_PERMISSIONS,
  getRolePermissionsMap,
} from "../../helpers/permissions";

type Role = "admin" | "manager" | "staff";

type UserRow = {
  id: string;
  username: string;
  firstname: string;
  lastname: string;
  name: string;
  email: string;
  phone: string;
  role_id: number;
  role: Role;
  status: string;
  date_created: string;
};

const ROLE_LABELS: Record<Role, string> = {
  admin: "Admin",
  manager: "Manager",
  staff: "Staff",
};

const ROLE_COLORS: Record<Role, string> = {
  admin: "danger",
  manager: "info",
  staff: "success",
};

const roles: Role[] = ["admin", "manager", "staff"];

const API_USERS_GET = "https://apidb.americanplaquecompany.com/analytics/users";
const API_USERS_ADD = "https://apidb.americanplaquecompany.com/analytics/users/add";
const API_USERS_UPDATE = "https://apidb.americanplaquecompany.com/analytics/users/update";
const API_USERS_CHANGE_PASSWORD =
  "https://apidb.americanplaquecompany.com/analytics/users/change-password";

const UserManagementPage: React.FC = () => {
  document.title = "User Management | APC Sales Analytics";
  const { hasPermission } = usePermissions();
  const rolePermissions = getRolePermissionsMap();

  const [users, setUsers] = useState<UserRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [changingPassword, setChangingPassword] = useState(false);

  const [search, setSearch] = useState("");
  const [roleFilter, setRoleFilter] = useState("all");
  const [statusFilter, setStatusFilter] = useState("all");

  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [activeEditTab, setActiveEditTab] = useState<"details" | "password">(
    "details"
  );

  const [username, setUsername] = useState("");
  const [firstname, setFirstname] = useState("");
  const [lastname, setLastname] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [roleId, setRoleId] = useState<number>(3);
  const [status, setStatus] = useState("active");
  const [password, setPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");

  const [showAddPassword, setShowAddPassword] = useState(false);
  const [showNewPassword, setShowNewPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);

  const normalizeRole = (roleId: any): Role => {
    const id = Number(roleId);
    if (id === 1) return "admin";
    if (id === 2) return "manager";
    return "staff";
  };

  const capitalize = (str: string) =>
    str ? str.charAt(0).toUpperCase() + str.slice(1) : "";

  const resetForm = () => {
    setEditingId(null);
    setActiveEditTab("details");
    setUsername("");
    setFirstname("");
    setLastname("");
    setEmail("");
    setPhone("");
    setRoleId(3);
    setStatus("active");
    setPassword("");
    setNewPassword("");
    setConfirmPassword("");
    setShowAddPassword(false);
    setShowNewPassword(false);
    setShowConfirmPassword(false);
  };

  const openAddModal = () => {
    resetForm();
    setDialogOpen(true);
  };

  const openEditModal = (user: UserRow) => {
    setEditingId(user.id);
    setActiveEditTab("details");
    setUsername(user.username);
    setFirstname(user.firstname);
    setLastname(user.lastname);
    setEmail(user.email);
    setPhone(user.phone);
    setRoleId(user.role_id);
    setStatus(user.status || "active");
    setPassword("");
    setNewPassword("");
    setConfirmPassword("");
    setShowAddPassword(false);
    setShowNewPassword(false);
    setShowConfirmPassword(false);
    setDialogOpen(true);
  };

  const fetchUsers = async () => {
    try {
      setLoading(true);

      const res = await fetch(API_USERS_GET);
      const data = await res.json();

      if (data?.status === "success" || Array.isArray(data?.users)) {
        const rows = Array.isArray(data?.users)
          ? data.users
          : Array.isArray(data?.data)
          ? data.data
          : [];

        const mapped: UserRow[] = rows.map((u: any, index: number) => {
          const role = normalizeRole(u.role_id);

          return {
            id: String(u.id ?? index),
            username: String(u.username ?? ""),
            firstname: String(u.firstname ?? ""),
            lastname: String(u.lastname ?? ""),
            name:
              `${String(u.firstname ?? "").trim()} ${String(
                u.lastname ?? ""
              ).trim()}`.trim() || "Unnamed User",
            email: String(u.email ?? ""),
            phone: String(u.phone ?? ""),
            role_id: Number(u.role_id ?? 3),
            role,
            status: String(u.status ?? "inactive"),
            date_created: String(u.date_created ?? ""),
          };
        });

        setUsers(mapped);
      } else {
        setUsers([]);
        toast.error(data?.message || "Failed to load users.");
      }
    } catch (err) {
      console.error("Error fetching users:", err);
      setUsers([]);
      toast.error("Failed to load users.");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchUsers();
  }, []);

  const filteredUsers = useMemo(() => {
    const q = search.trim().toLowerCase();

    return users.filter((u) => {
      if (
        q &&
        !u.name.toLowerCase().includes(q) &&
        !u.email.toLowerCase().includes(q) &&
        !u.username.toLowerCase().includes(q)
      ) {
        return false;
      }

      if (roleFilter !== "all" && u.role !== roleFilter) {
        return false;
      }

      if (
        statusFilter !== "all" &&
        String(u.status).toLowerCase() !== statusFilter.toLowerCase()
      ) {
        return false;
      }

      return true;
    });
  }, [users, search, roleFilter, statusFilter]);

  const handleSave = async () => {
    if (!hasPermission(APP_PERMISSIONS.usersManage)) return;

    if (!firstname.trim()) {
      toast.error("First name is required.");
      return;
    }
    if (!lastname.trim()) {
      toast.error("Last name is required.");
      return;
    }
    if (!username.trim()) {
      toast.error("Username is required.");
      return;
    }
    if (!email.trim()) {
      toast.error("Email is required.");
      return;
    }

    if (!editingId && !password.trim()) {
      toast.error("Password is required.");
      return;
    }

    const payload = {
      ...(editingId ? { id: Number(editingId) } : {}),
      username: username.trim(),
      firstname: firstname.trim(),
      lastname: lastname.trim(),
      email: email.trim(),
      phone: phone.trim(),
      role_id: Number(roleId),
      status: status.trim().toLowerCase(),
      ...(!editingId ? { password: password.trim() } : {}),
    };

    try {
      setSaving(true);

      const url = editingId ? API_USERS_UPDATE : API_USERS_ADD;

      const res = await fetch(url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(payload),
      });

      const data = await res.json();

      if (data?.status === "success") {
        toast.success(
          editingId ? "User updated successfully." : "User created successfully."
        );
        await fetchUsers();
        setDialogOpen(false);
        resetForm();
      } else {
        toast.error(data?.message || "Failed to save user.");
      }
    } catch (err) {
      console.error("Save user error:", err);
      toast.error("Failed to save user.");
    } finally {
      setSaving(false);
    }
  };

  const handleChangePassword = async () => {
    if (!editingId) return;

    if (!newPassword.trim()) {
      toast.error("New password is required.");
      return;
    }

    if (newPassword.trim().length < 8) {
      toast.error("New password must be at least 8 characters.");
      return;
    }

    if (newPassword !== confirmPassword) {
      toast.error("Password confirmation does not match.");
      return;
    }

    try {
      setChangingPassword(true);

      const res = await fetch(API_USERS_CHANGE_PASSWORD, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          id: Number(editingId),
          newPassword: newPassword.trim(),
        }),
      });

      const data = await res.json();

      if (data?.status === "success") {
        toast.success(data?.message || "Password updated successfully.");
        setNewPassword("");
        setConfirmPassword("");
        setDialogOpen(false);
        resetForm();
      } else {
        toast.error(data?.message || "Failed to change password.");
      }
    } catch (err) {
      console.error("Change password error:", err);
      toast.error("Failed to change password.");
    } finally {
      setChangingPassword(false);
    }
  };

  return (
    <div className="page-content">
      <ToastContainer position="top-right" autoClose={3000} />
      <Container fluid>
        <BreadCrumb title="User Management" pageTitle="Dashboard" />

        <Row className="mb-4 align-items-center">
          <Col md={8}>
            <div className="d-flex align-items-center gap-2 mb-1">
              <UserCog size={22} className="text-primary" />
              <h2 className="mb-0">User Management</h2>
            </div>
            <p className="text-muted mb-0">Manage users, roles and permissions</p>
          </Col>

          <Col md={4} className="text-md-end mt-3 mt-md-0">
            <div className="d-flex justify-content-md-end gap-2">
              <Button
                tag={Link}
                to="/roles-permissions"
                color="light"
                className="border d-inline-flex align-items-center gap-2"
                disabled={!hasPermission(APP_PERMISSIONS.rolesManage)}
              >
                <Shield size={14} /> Manage Roles
              </Button>
              <Button
                color="primary"
                className="d-inline-flex align-items-center gap-2"
                onClick={openAddModal}
                disabled={!hasPermission(APP_PERMISSIONS.usersManage)}
              >
                <Plus size={14} /> Add User
              </Button>
            </div>
          </Col>
        </Row>

        <Row className="mb-4">
          <Col>
            <Card className="border-0 shadow-sm">
              <CardHeader className="border-0 pb-0">
                <Row className="g-3">
                  <Col lg={6}>
                    <Label className="form-label">Search</Label>
                    <InputGroup>
                      <InputGroupText>
                        <Search size={16} />
                      </InputGroupText>
                      <Input
                        placeholder="Search by name, email, or username..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                      />
                    </InputGroup>
                  </Col>

                  <Col lg={3}>
                    <Label className="form-label">Role</Label>
                    <Input
                      type="select"
                      value={roleFilter}
                      onChange={(e) => setRoleFilter(e.target.value)}
                    >
                      <option value="all">All Roles</option>
                      <option value="admin">Admin</option>
                      <option value="manager">Manager</option>
                      <option value="staff">Staff</option>
                    </Input>
                  </Col>

                  <Col lg={3}>
                    <Label className="form-label">Status</Label>
                    <Input
                      type="select"
                      value={statusFilter}
                      onChange={(e) => setStatusFilter(e.target.value)}
                    >
                      <option value="all">All Statuses</option>
                      <option value="active">Active</option>
                      <option value="inactive">Inactive</option>
                    </Input>
                  </Col>
                </Row>
              </CardHeader>

              <CardBody>
                <div className="table-responsive">
                  <table className="table table-hover align-middle mb-0">
                    <thead>
                      <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Permissions</th>
                        <th>Status</th>
                        <th>Date Created</th>
                        <th></th>
                      </tr>
                    </thead>

                    <tbody>
                      {loading && (
                        <tr>
                          <td colSpan={6} className="text-center py-4">
                            <div className="d-inline-flex align-items-center gap-2 text-muted">
                              <Spinner size="sm" color="primary" />
                              <span>Loading users...</span>
                            </div>
                          </td>
                        </tr>
                      )}

                      {!loading &&
                        filteredUsers.map((u) => (
                          <tr key={u.id}>
                            <td>
                              <div className="d-flex align-items-center gap-3">
                                <div
                                  className="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold"
                                  style={{ width: 34, height: 34, fontSize: 12 }}
                                >
                                  {u.name.charAt(0)}
                                </div>

                                <div>
                                  <div className="fw-medium">{u.name}</div>
                                  <small className="text-muted d-block">{u.email}</small>
                                  <small className="text-muted">@{u.username}</small>
                                </div>
                              </div>
                            </td>

                            <td>
                              <Badge color={ROLE_COLORS[u.role]}>
                                {ROLE_LABELS[u.role]}
                              </Badge>
                            </td>

                            <td>
                              <small className="text-muted">
                                {rolePermissions[u.role].length} permissions
                              </small>
                            </td>

                            <td>
                              <Badge color={u.status === "active" ? "success" : "secondary"}>
                                {capitalize(u.status || "")}
                              </Badge>
                            </td>

                            <td>
                              {u.date_created
                                ? new Date(u.date_created).toLocaleDateString()
                                : "-"}
                            </td>

                            <td>
                              <Button
                                color="light"
                                className="border btn-sm"
                                onClick={() => openEditModal(u)}
                                disabled={!hasPermission(APP_PERMISSIONS.usersManage)}
                              >
                                <Edit size={14} />
                              </Button>
                            </td>
                          </tr>
                        ))}

                      {!loading && filteredUsers.length === 0 && (
                        <tr>
                          <td colSpan={6} className="text-center text-muted py-4">
                            No users found.
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>
              </CardBody>
            </Card>
          </Col>
        </Row>

        <Row>
          <Col>
            <Card className="border-0 shadow-sm">
              <CardHeader className="border-0 pb-0">
                <h6 className="mb-0 d-flex align-items-center gap-2">
                  <Shield size={16} /> Roles & Permissions Matrix
                </h6>
              </CardHeader>

              <CardBody>
                <div className="table-responsive">
                  <table className="table table-sm align-middle mb-0">
                    <thead>
                      <tr>
                        <th>Permission</th>
                        {roles.map((r) => (
                          <th key={r} className="text-center">
                            {ROLE_LABELS[r]}
                          </th>
                        ))}
                      </tr>
                    </thead>

                    <tbody>
                      {rolePermissions.admin.map((perm) => (
                        <tr key={perm}>
                          <td>
                            <code>{perm}</code>
                          </td>

                          {roles.map((r) => (
                            <td key={r} className="text-center">
                              {rolePermissions[r].includes(perm) ? (
                                <span className="text-success fw-bold">&#10003;</span>
                              ) : (
                                <span className="text-muted">-</span>
                              )}
                            </td>
                          ))}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </CardBody>
            </Card>
          </Col>
        </Row>

        <Modal
          isOpen={dialogOpen}
          toggle={() => !saving && !changingPassword && setDialogOpen(false)}
          centered
        >
          <ModalHeader toggle={() => !saving && !changingPassword && setDialogOpen(false)}>
            {editingId ? "Edit User" : "Add User"}
          </ModalHeader>

          <ModalBody>
            {editingId ? (
              <>
                <Nav tabs className="mb-3">
                  <NavItem>
                    <NavLink
                      href="#"
                      active={activeEditTab === "details"}
                      onClick={(e) => {
                        e.preventDefault();
                        setActiveEditTab("details");
                      }}
                    >
                      Details
                    </NavLink>
                  </NavItem>
                  <NavItem>
                    <NavLink
                      href="#"
                      active={activeEditTab === "password"}
                      onClick={(e) => {
                        e.preventDefault();
                        setActiveEditTab("password");
                      }}
                    >
                      Change Password
                    </NavLink>
                  </NavItem>
                </Nav>

                <TabContent activeTab={activeEditTab}>
                  <TabPane tabId="details">
                    <Row className="g-3">
                      <Col md={6}>
                        <Label className="form-label">Username *</Label>
                        <Input
                          value={username}
                          onChange={(e) => setUsername(e.target.value)}
                          disabled={saving || changingPassword}
                        />
                      </Col>

                      <Col md={6}>
                        <Label className="form-label">Email *</Label>
                        <Input
                          type="email"
                          value={email}
                          onChange={(e) => setEmail(e.target.value)}
                          disabled={saving || changingPassword}
                        />
                      </Col>

                      <Col md={6}>
                        <Label className="form-label">First Name *</Label>
                        <Input
                          value={firstname}
                          onChange={(e) => setFirstname(e.target.value)}
                          disabled={saving || changingPassword}
                        />
                      </Col>

                      <Col md={6}>
                        <Label className="form-label">Last Name *</Label>
                        <Input
                          value={lastname}
                          onChange={(e) => setLastname(e.target.value)}
                          disabled={saving || changingPassword}
                        />
                      </Col>

                      <Col md={6}>
                        <Label className="form-label">Phone</Label>
                        <Input
                          value={phone}
                          onChange={(e) => setPhone(e.target.value)}
                          disabled={saving || changingPassword}
                        />
                      </Col>

                      <Col md={6}>
                        <Label className="form-label">Role *</Label>
                        <Input
                          type="select"
                          value={roleId}
                          onChange={(e) => setRoleId(Number(e.target.value))}
                          disabled={saving || changingPassword}
                        >
                          <option value={1}>Admin</option>
                          <option value={2}>Manager</option>
                          <option value={3}>Staff</option>
                        </Input>
                      </Col>

                      <Col md={12}>
                        <Label className="form-label">Status</Label>
                        <Input
                          type="select"
                          value={status}
                          onChange={(e) => setStatus(e.target.value)}
                          disabled={saving || changingPassword}
                        >
                          <option value="active">Active</option>
                          <option value="inactive">Inactive</option>
                        </Input>
                      </Col>

                      <Col md={12} className="d-flex justify-content-end gap-2 pt-2 border-top">
                        <Button
                          color="light"
                          className="border"
                          onClick={() => setDialogOpen(false)}
                          disabled={saving || changingPassword}
                        >
                          Cancel
                        </Button>

                        <Button
                          color="primary"
                          onClick={handleSave}
                          disabled={
                            saving ||
                            changingPassword ||
                            !hasPermission(APP_PERMISSIONS.usersManage)
                          }
                        >
                          {saving ? (
                            <span className="d-inline-flex align-items-center gap-2">
                              <Spinner size="sm" />
                              Saving...
                            </span>
                          ) : (
                            "Update User"
                          )}
                        </Button>
                      </Col>
                    </Row>
                  </TabPane>

                  <TabPane tabId="password">
                    <Row className="g-3">
                      <Col md={6}>
                        <Label className="form-label">New Password</Label>
                        <InputGroup>
                          <Input
                            type={showNewPassword ? "text" : "password"}
                            value={newPassword}
                            onChange={(e) => setNewPassword(e.target.value)}
                            disabled={saving || changingPassword}
                            placeholder="Minimum 8 characters"
                          />
                          <Button
                            color="light"
                            className="border"
                            type="button"
                            onClick={() => setShowNewPassword((v) => !v)}
                            disabled={saving || changingPassword}
                          >
                            {showNewPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                          </Button>
                        </InputGroup>
                      </Col>

                      <Col md={6}>
                        <Label className="form-label">Confirm Password</Label>
                        <InputGroup>
                          <Input
                            type={showConfirmPassword ? "text" : "password"}
                            value={confirmPassword}
                            onChange={(e) => setConfirmPassword(e.target.value)}
                            disabled={saving || changingPassword}
                            invalid={
                              Boolean(confirmPassword) && confirmPassword !== newPassword
                            }
                          />
                          <Button
                            color="light"
                            className="border"
                            type="button"
                            onClick={() => setShowConfirmPassword((v) => !v)}
                            disabled={saving || changingPassword}
                          >
                            {showConfirmPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                          </Button>
                        </InputGroup>
                      </Col>

                      <Col md={12} className="d-flex justify-content-end gap-2 pt-2 border-top">
                        <Button
                          color="light"
                          className="border"
                          onClick={() => setDialogOpen(false)}
                          disabled={saving || changingPassword}
                        >
                          Cancel
                        </Button>

                        <Button
                          color="warning"
                          onClick={handleChangePassword}
                          disabled={
                            changingPassword ||
                            saving ||
                            !hasPermission(APP_PERMISSIONS.usersManage)
                          }
                        >
                          {changingPassword ? (
                            <span className="d-inline-flex align-items-center gap-2">
                              <Spinner size="sm" />
                              Updating Password...
                            </span>
                          ) : (
                            "Change Password"
                          )}
                        </Button>
                      </Col>
                    </Row>
                  </TabPane>
                </TabContent>
              </>
            ) : (
              <Row className="g-3">
                <Col md={6}>
                  <Label className="form-label">Username *</Label>
                  <Input
                    value={username}
                    onChange={(e) => setUsername(e.target.value)}
                    disabled={saving}
                  />
                </Col>

                <Col md={6}>
                  <Label className="form-label">Password *</Label>
                  <InputGroup>
                    <Input
                      type={showAddPassword ? "text" : "password"}
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      disabled={saving}
                    />
                    <Button
                      color="light"
                      className="border"
                      type="button"
                      onClick={() => setShowAddPassword((v) => !v)}
                      disabled={saving}
                    >
                      {showAddPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                    </Button>
                  </InputGroup>
                </Col>

                <Col md={12}>
                  <Label className="form-label">Email *</Label>
                  <Input
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    disabled={saving}
                  />
                </Col>

                <Col md={6}>
                  <Label className="form-label">First Name *</Label>
                  <Input
                    value={firstname}
                    onChange={(e) => setFirstname(e.target.value)}
                    disabled={saving}
                  />
                </Col>

                <Col md={6}>
                  <Label className="form-label">Last Name *</Label>
                  <Input
                    value={lastname}
                    onChange={(e) => setLastname(e.target.value)}
                    disabled={saving}
                  />
                </Col>

                <Col md={6}>
                  <Label className="form-label">Phone</Label>
                  <Input
                    value={phone}
                    onChange={(e) => setPhone(e.target.value)}
                    disabled={saving}
                  />
                </Col>

                <Col md={6}>
                  <Label className="form-label">Role *</Label>
                  <Input
                    type="select"
                    value={roleId}
                    onChange={(e) => setRoleId(Number(e.target.value))}
                    disabled={saving}
                  >
                    <option value={1}>Admin</option>
                    <option value={2}>Manager</option>
                    <option value={3}>Staff</option>
                  </Input>
                </Col>

                <Col md={12}>
                  <Label className="form-label">Status</Label>
                  <Input
                    type="select"
                    value={status}
                    onChange={(e) => setStatus(e.target.value)}
                    disabled={saving}
                  >
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                  </Input>
                </Col>

                <Col md={12} className="d-flex justify-content-end gap-2 pt-2 border-top">
                  <Button
                    color="light"
                    className="border"
                    onClick={() => setDialogOpen(false)}
                    disabled={saving}
                  >
                    Cancel
                  </Button>

                  <Button
                    color="primary"
                    onClick={handleSave}
                    disabled={saving || !hasPermission(APP_PERMISSIONS.usersManage)}
                  >
                    {saving ? (
                      <span className="d-inline-flex align-items-center gap-2">
                        <Spinner size="sm" />
                        Saving...
                      </span>
                    ) : (
                      "Save User"
                    )}
                  </Button>
                </Col>
              </Row>
            )}
          </ModalBody>
        </Modal>
      </Container>
    </div>
  );
};

export default UserManagementPage;