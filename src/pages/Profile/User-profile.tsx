import React, { useState, useEffect } from "react";
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Col,
  Container,
  Form,
  Input,
  Label,
  Nav,
  NavItem,
  NavLink,
  Row,
  TabContent,
  TabPane,
  Spinner,
  Badge,
  Modal,
  ModalHeader,
  ModalBody,
} from "reactstrap";
import classnames from "classnames";
import Swal from "sweetalert2";
import { ToastContainer, toast } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import { APIClient } from "../../helpers/api_helper";
import config from "../../config";
import ActivityLogs from "../Users/ActivityLogs";
import { getAuthUser } from "../../helpers/auth_storage";
import BreadCrumb from "../../Components/Common/BreadCrumb";

type ApiStatus = "success" | "error";

type UserDetailsResponse =
  | { status: "error"; message?: string }
  | { status: "success"; user?: any; message?: string };

type ActivityLogsResponse =
  | { status: ApiStatus; logs?: any[]; message?: string };

type UserState = {
  id: string;
  username: string;
  firstname: string;
  lastname: string;
  email: string;
  phone: string;
  address: string;
  city: string;
  country: string;
  zip: string;
  status: string;
  avatar: string | null;
  avatarFile: File | null;
};

const Settings: React.FC = () => {
  const apipost = new APIClient();

  const [pageloading, setPageLoading] = useState(true);
  const [loading, setLoading] = useState(false);
  const [activeTab, setActiveTab] = useState("1");

  const [logsOpen, setLogsOpen] = useState(false);
  const [logsMaximized, setLogsMaximized] = useState(false);
  const [logs, setLogs] = useState<any[]>([]);
  const [loadingLogs, setLoadingLogs] = useState(false);

  const [user, setUser] = useState<UserState>({
    id: "",
    username: "",
    firstname: "",
    lastname: "",
    email: "",
    phone: "",
    address: "",
    city: "",
    country: "",
    zip: "",
    status: "",
    avatar: null as string | null,
    avatarFile: null as File | null,
  });

  const [formData, setFormData] = useState({
    oldPassword: "",
    newPassword: "",
    confirmPassword: "",
  });

  const countryList = [
    "United States","United Kingdom","Philippines","Canada","Australia",
    "Germany","France","Japan","Singapore","India","China","Brazil",
    "South Africa","Mexico","Italy","Spain","Vietnam","Thailand",
    "Malaysia","Netherlands","Sweden","Norway","Switzerland","Others",
  ];
  const previewAvatar = user.avatarFile ? URL.createObjectURL(user.avatarFile) : null;

  const prefixUrl = (url: string | null | undefined) => {
    const base = (config?.api?.API_URL || "").replace(/\/$/, "");
    if (!url) return `${base}/images/noavatar.png`;
    if (url.startsWith("http")) return url;
    return `${base}/${url.replace(/^\//, "")}`;
  };

  // ================================
  // Load user profile
  // ================================
const fetchUser = async () => {
  try {
    debugger;
    const raw: any = getAuthUser();
    if (!raw) {
      setPageLoading(false);
      return;
    }

    const obj = raw; // JSON.parse(raw);

    const r = await apipost.post<UserDetailsResponse>("/users/details", {
      id: obj.id,
      uid: obj.id,
    });

    if (r?.status === "error") {
      Swal.fire({
        icon: "error",
        text: r?.message || "Missing ID not found. Try to reopen the page.",
        confirmButtonText: "OK",
      });
      setPageLoading(false);
      return;
    }

    if ((r as any)?.user) {
      const u = (r as any).user;
      setUser((prev) => ({
        ...prev,
        ...u,
        avatar: u.avatar,
        avatarFile: null,
      }));
    }

    setPageLoading(false);
  } catch (error) {
    console.error("Error fetching user data:", error);
    setPageLoading(false);
  }
};


  useEffect(() => {
    fetchUser();
  }, []);

  // ================================
  // Load activity logs
  // ================================
  const loadLogs = async () => {
    if (!user.id) return;

    setLoadingLogs(true);
    try {
      const r = await apipost.post<ActivityLogsResponse>("/users/activitylogs", { id: user.id });
      setLogs(r?.logs || []);
    } catch {
      Swal.fire({ icon: "error", text: "Failed to load activity logs", confirmButtonText: "OK" });
    } finally {
      setLoadingLogs(false);
    }
  };


  // ================================
  // Handlers
  // ================================
  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setUser((prevUser) => ({ ...prevUser, [name]: value } as UserState));
  };

  const handleAvatarChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      const file = e.target.files[0];
      setUser((prevUser) => ({
        ...prevUser,
        avatarFile: file, // keep file for upload
      }));
    }
  };

  const handleInputPasswordChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { id, value } = e.target;
    setFormData((prev) => ({ ...prev, [id]: value }));
  };

  // ================================
  // Save profile
  // ================================
  const updateProfile = async () => {
    setLoading(true);
    try {
      const fd = new FormData();
      const userEntries = Object.entries(user) as Array<[keyof UserState, UserState[keyof UserState]]>;
      userEntries.forEach(([k, value]) => {
        if (value !== null && value !== "") {
          if (k === "avatarFile" && user.avatarFile) {
            fd.append("avatar", user.avatarFile);
          } else if (k !== "avatarFile") {
            fd.append(k, String(value));
          }
        }
      });

      const response = await apipost.post("/users/save", fd, {
        headers: { "Content-Type": "multipart/form-data" },
      });

      if (response.status === "success") {
        toast.success(response.message || "Profile updated successfully.");
      } else {
        toast.error(response.message || "Failed to update profile.");
      }

      Swal.fire({
        icon: response.status === "success" ? "success" : "error",
        text: response.message || "Profile updated",
        confirmButtonText: "OK",
      });

      if (response.status === "success") {
        fetchUser(); // reload from backend
      }
    } catch (error) {
      toast.error("Error while saving user details.");
      Swal.fire({ icon: "error", text: "Error while saving!", confirmButtonText: "OK" });
    }
    setLoading(false);
  };

  // ================================
  // Change password
  // ================================
  const validatePasswords = async () => {
    const { oldPassword, newPassword, confirmPassword } = formData;

    if (!oldPassword || !newPassword || !confirmPassword) {
      Swal.fire({ icon: "error", text: "All fields are required.", confirmButtonText: "OK" });
      return;
    }
    if (newPassword !== confirmPassword) {
      Swal.fire({
        icon: "error",
        text: "New password and confirm password do not match.",
        confirmButtonText: "OK",
      });
      return;
    }

    setLoading(true);
    try {
      const response = await apipost.post("/users/changepass", {
        id: user.id,
        oldPassword: oldPassword,
        newPassword: newPassword,
      });

      if (response.status === "success") {
        toast.success(response.message || "Password updated successfully.");
      } else {
        toast.error(response.message || "Failed to update password.");
      }

      Swal.fire({
        icon: response.status === "success" ? "success" : "error",
        text: response.message || "Password updated",
        confirmButtonText: "OK",
      });

      setFormData({ oldPassword: "", newPassword: "", confirmPassword: "" });
    } catch (error) {
      toast.error("Error while changing password.");
      Swal.fire({ icon: "error", text: "Error while changing password!", confirmButtonText: "OK" });
    }
    setLoading(false);
  };

  const tabChange = (tab: string) => {
    if (activeTab !== tab) setActiveTab(tab);
  };

  useEffect(() => {
    return () => {
      if (previewAvatar) URL.revokeObjectURL(previewAvatar);
    };
  }, [previewAvatar]);

  return (
    <div className="page-content">
      <ToastContainer position="top-right" autoClose={3000} />
      {pageloading ? (
        <Container fluid>
          <div className="d-flex justify-content-center align-items-center" style={{ minHeight: "300px" }}>
            <Spinner color="primary" style={{ width: "3rem", height: "3rem" }} />
          </div>
        </Container>
      ) : (
        <Container fluid>
          <BreadCrumb title="Profile" pageTitle="Dashboard" />
          {/* Profile Header */}
          <Row className="mb-4">
            <Col lg="9">
              <Card className="shadow-sm border-0">
                <CardBody>
                  <Row className="align-items-center">
                    {/* Avatar */}
                    <Col md="4" className="text-center">
                      <img
                        src={ previewAvatar ? previewAvatar : prefixUrl(user.avatar)                        }
                        alt="User Avatar"
                        className="rounded-circle shadow-sm mb-2"
                        style={{ objectFit: "cover", width: "120px", height: "120px" }}
                      />
                      <Input type="file" accept="image/*" onChange={handleAvatarChange} />
                    </Col>
                    {/* Info */}
                    <Col md="8">
                      <h5 className="mt-3">{user.firstname} {user.lastname}</h5>
                      <p className="text-muted d-inline-flex align-items-center" style={{ gap: "10px" }}>
                        @{user.username}
                        <Badge color={user.status === "active" ? "success" : "danger"} pill>
                          {user.status}
                        </Badge>
                      </p>
                      <p><i className="ri-mail-line me-1"></i> {user.email}</p>
                      <p><i className="ri-phone-line me-1"></i> {user.phone}</p>
                      <p>
                        <i className="ri-map-pin-line me-1"></i>
                        {user.address}, {user.city}, {user.country} {user.zip}
                      </p>
                      <Button
                        size="sm"
                        color="info"
                        onClick={() => {
                          setLogsOpen(true);
                          loadLogs();
                        }}
                      >
                        <i className="ri-file-list-2-line me-1"></i> View Activity Logs
                      </Button>
                    </Col>
                  </Row>
                </CardBody>
              </Card>
            </Col>
          </Row>

          {/* Tabs */}
          <Row>
            <Col xxl={9}>
              <Card>
                <CardHeader>
                  <Nav className="nav-tabs-custom rounded card-header-tabs border-bottom-0">
                    <NavItem>
                      <NavLink
                        to="#"
                        className={classnames({ active: activeTab === "1" })}
                        onClick={() => tabChange("1")}
                      >
                        Account Details
                      </NavLink>
                    </NavItem>
                    <NavItem>
                      <NavLink
                        to="#"
                        className={classnames({ active: activeTab === "2" })}
                        onClick={() => tabChange("2")}
                      >
                        Change Password
                      </NavLink>
                    </NavItem>
                  </Nav>
                </CardHeader>
                <CardBody className="p-4">
                  <TabContent activeTab={activeTab}>
                    {/* Account Details */}
                    <TabPane tabId="1">
                      <Form>
                        <Row className="g-3">
                          <Col lg={6}>
                            <Label>First Name</Label>
                            <Input type="text" name="firstname" value={user.firstname || ""} onChange={handleInputChange} />
                          </Col>
                          <Col lg={6}>
                            <Label>Last Name</Label>
                            <Input type="text" name="lastname" value={user.lastname || ""} onChange={handleInputChange} />
                          </Col>
                          <Col lg={6}>
                            <Label>Phone</Label>
                            <Input type="text" name="phone" value={user.phone || ""} onChange={handleInputChange} />
                          </Col>
                          <Col lg={6}>
                            <Label>Email</Label>
                            <Input type="email" name="email" value={user.email || ""} onChange={handleInputChange} />
                          </Col>
                          <Col lg={12}>
                            <Label>Address</Label>
                            <Input type="text" name="address" value={user.address || ""} onChange={handleInputChange} />
                          </Col>
                          <Col lg={4}>
                            <Label>City</Label>
                            <Input type="text" name="city" value={user.city || ""} onChange={handleInputChange} />
                          </Col>
                          <Col lg={4}>
                            <Label>Country</Label>
                            <Input type="select" name="country" value={user.country || ""} onChange={handleInputChange}>
                              <option value="">Select Country</option>
                              {countryList.map((c, idx) => (
                                <option key={idx} value={c}>{c}</option>
                              ))}
                            </Input>
                          </Col>
                          <Col lg={4}>
                            <Label>zip</Label>
                            <Input type="text" name="zip" value={user.zip || ""} onChange={handleInputChange} />
                          </Col>
                          <Col lg={12} className="mt-3">
                            <Button 
                              color="warning" 
                              onClick={updateProfile} 
                              disabled={loading}
                              className="d-flex align-items-center justify-content-center"
                              style={{ minWidth: "150px" }}  // adjust width so spinner + text fit
                            >
                              {loading ? (
                                <Spinner size="sm" />
                              ) : (
                                "Update Profile"
                              )}
                            </Button>
                          </Col>
                        </Row>
                      </Form>
                    </TabPane>

                    {/* Change Password */}
                    <TabPane tabId="2">
                      <Form>
                        <Row className="g-3">
                          <Col lg={4}>
                            <Label>Old Password</Label>
                            <Input type="password" id="oldPassword" value={formData.oldPassword} onChange={handleInputPasswordChange} />
                          </Col>
                          <Col lg={4}>
                            <Label>New Password</Label>
                            <Input type="password" id="newPassword" value={formData.newPassword} onChange={handleInputPasswordChange} />
                          </Col>
                          <Col lg={4}>
                            <Label>Confirm Password</Label>
                            <Input type="password" id="confirmPassword" value={formData.confirmPassword} onChange={handleInputPasswordChange} />
                          </Col>
                          <Col lg={12} className="mt-3">
                            <Button color="warning" onClick={validatePasswords} disabled={loading}>
                              {loading ? <Spinner size="sm" /> : "Update Password"}
                            </Button>
                          </Col>
                        </Row>
                      </Form>
                    </TabPane>
                  </TabContent>
                </CardBody>
              </Card>
            </Col>
          </Row>

          {/* Activity Logs Modal */}
          <Modal
            isOpen={logsOpen}
            toggle={() => setLogsOpen(false)}
            size={logsMaximized ? "xl" : "lg"}
            fullscreen={logsMaximized}
            centered
          >
<ModalHeader toggle={() => setLogsOpen(false)}>
  <div className="d-flex align-items-center w-100">
    <div>
      <i className="ri-file-list-2-line me-2"></i> Activity Logs
    </div>
    <div  style={{ marginLeft: "10px" }}>
      <Button
        size="sm"
        color="light"
        onClick={() => setLogsMaximized(!logsMaximized)}
        title={logsMaximized ? "Restore" : "Maximize"}
      >
        {logsMaximized ? (
          <i className="ri-contract-left-right-line"></i>
        ) : (
          <i className="ri-fullscreen-line"></i>
        )}
      </Button>
    </div>
  </div>
</ModalHeader>
            <ModalBody>
              <ActivityLogs logs={logs} loading={loadingLogs} />
            </ModalBody>
          </Modal>
        </Container>
      )}
    </div>
  );
};

export default Settings;

