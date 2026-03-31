import React, { useState, useEffect } from 'react';
import { useSelector } from 'react-redux';
import { Link } from 'react-router-dom';
import { Dropdown, DropdownItem, DropdownMenu, DropdownToggle } from 'reactstrap';
import { useLocation } from 'react-router-dom';

//import images
import avatar1 from "../../assets/images/users/user-dummy-img.jpg";
import { createSelector } from 'reselect';
import config from "../../config";
import { getAuthUser } from "../../helpers/auth_storage";

const ProfileDropdown = () => {
    const location = useLocation();
    const profiledropdownData = createSelector(
        (state) => state.Profile,
        (user) => user.user
      );
    // Inside your component
    const user = useSelector(profiledropdownData);

    const [userName, setUserName] = useState("");
    const [role, setRole] = useState("");
    const [avatar, setAvatar] = useState("");

    const prefixUrl = (url: string) => {
        const base = config?.api?.API_URL ? config.api.API_URL.replace(/\/$/, "") : "";
        if (!url) return base + "/images/noavatar.png";
        if (url.startsWith("http")) return url;
        return base + "/" + url.replace(/^\//, "");
    };

    useEffect(() => {
         const obj: any = getAuthUser();
        if (!obj) return;

        setUserName(`${obj?.username ?? ""} (#${obj?.id ?? ""})`);
        setRole(obj?.rank ?? "");
        setAvatar(prefixUrl(obj?.avatar ?? ""));
    }, [user, location.pathname]);

    //Dropdown Toggle
    const [isProfileDropdown, setIsProfileDropdown] = useState(false);
    const toggleProfileDropdown = () => {
        setIsProfileDropdown(!isProfileDropdown);
    };
    return (
        <React.Fragment>
            <Dropdown isOpen={isProfileDropdown} toggle={toggleProfileDropdown} className="ms-sm-3 header-item topbar-user">
                <DropdownToggle tag="button" type="button" className="btn">
                    <span className="d-flex align-items-center">
                        <img className="rounded-circle header-profile-user" src={avatar || avatar1}
                            alt="Header Avatar" />
                        <span className="text-start ms-xl-2">
                            <span className="d-none d-xl-inline-block ms-1 fw-medium user-name-text">{userName}</span>
                            <span className="d-none d-xl-block ms-1 fs-12 text-muted user-name-sub-text">{role}</span>
                        </span>
                    </span>
                </DropdownToggle>
                <DropdownMenu className="dropdown-menu-end">
                    <h6 className="dropdown-header">Welcome {userName}</h6>
                    {/* <DropdownItem className='p-0'>
                        <Link to={process.env.PUBLIC_URL + "#"} className="dropdown-item">
                            <i
                                className="mdi mdi-wallet text-muted fs-16 align-middle me-1"></i> <span
                                    className="align-middle">Wallet Balance : <b>$100.00</b></span>
                        </Link>
                    </DropdownItem > */}
                    <DropdownItem className='p-0'>
                        <Link to="/profile" className="dropdown-item">
                            <i className="mdi mdi-account-circle text-muted fs-16 align-middle me-1"></i>
                            <span className="align-middle">Profile</span>
                        </Link>
                    </DropdownItem>
                    {/* <DropdownItem className='p-0'>
                        <Link to="/referrals" className="dropdown-item">
                            <i className="mdi mdi-account-multiple-outline fs-16 me-1"></i>
                            <span className="align-middle">Referrals</span>
                        </Link>
                    </DropdownItem>
                    <DropdownItem className='p-0'>
                        <Link to="/sitelinks" className="dropdown-item">
                            <i className="mdi mdi-link-variant fs-16 me-1"></i>
                            <span className="align-middle">Initiatives</span>
                        </Link>
                    </DropdownItem> */}
                    {/* <DropdownItem className='p-0'>
                        <Link to={process.env.PUBLIC_URL + "#"} className="dropdown-item">
                            <i className="mdi mdi-message-text-outline text-muted fs-16 align-middle me-1"></i> <span
                                className="align-middle">Messages</span>
                        </Link>
                    </DropdownItem> */}
                    {/* <DropdownItem className='p-0'>
                        <Link to={"#"} className="dropdown-item">
                            <i className="mdi mdi-calendar-check-outline text-muted fs-16 align-middle me-1"></i> <span
                                className="align-middle">Taskboard</span>
                        </Link>
                    </DropdownItem> */}
                    {/* <DropdownItem className='p-0'>
                        <Link to={process.env.PUBLIC_URL + "#"} className="dropdown-item">
                            <i
                                className="mdi mdi-lifebuoy text-muted fs-16 align-middle me-1"></i> <span
                                    className="align-middle">Help</span>
                        </Link>
                    </DropdownItem> */}
                    <div className="dropdown-divider"></div>
                    
                    {/* <DropdownItem className='p-0'>
                        <Link to={process.env.PUBLIC_URL + "#"} className="dropdown-item">
                            <i
                                    className="mdi mdi-cog-outline text-muted fs-16 align-middle me-1"></i> <span
                                        className="align-middle">Settings</span>
                        </Link>
                    </DropdownItem> */}
                    {/* <DropdownItem className='p-0'>
                        <Link to={process.env.PUBLIC_URL + "#"} className="dropdown-item">
                            <i
                                className="mdi mdi-lock text-muted fs-16 align-middle me-1"></i> <span className="align-middle">Lock screen</span>
                        </Link>
                    </DropdownItem> */}
                    <DropdownItem className='p-0'>
                        <Link to="/logout" className="dropdown-item">
                            <i
                                className="mdi mdi-logout text-muted fs-16 align-middle me-1"></i> <span
                                    className="align-middle" data-key="t-logout">Logout</span>
                        </Link>
                    </DropdownItem>
                </DropdownMenu>
            </Dropdown>
        </React.Fragment>
    );
};

export default ProfileDropdown;
