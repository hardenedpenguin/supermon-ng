<?php include("session.inc"); ?>
<?php include "header.inc"; ?>
<p>
Welcome to <b><i><?php echo $CALL; ?></i></b> and associated AllStar nodes.   This Bridge runs on the lastest <a href="https://allstarlink.org">ASL3+ Distribution</a> of AllStar Link.
<br><br>
This Supermon-ng web site is for monitoring and managing ham radio 
<a href="http://allstarlink.org" target="_blank">AllStar</a> and app_rpt
node linking and micro-node.com RTCM clients. As of 2020 Micro-node has been shutdown. See the <a href="http://crompton.com/hamradio/RTCM/">RTCM Info page here</a>. 
This is Supermon2 version 1.xx which is a branch of Supermon 6.3.
</p>
<p>
On the menu bar click on the node numbers to see, and manage if you have a login ID, each local node. 
These pages dynamically display any remote nodes that are connected to it. 
When a signal is received the remote node will move to the top of the list and will have a dark-blue background. 
The most recently received nodes will always be at the top of the list. 
<ul>
<li>
The <b>Dir</b> column shows <b>IN</b> when another node connected to us and <b>OUT</b> if the connection was made from us. 
</li>
<li>
The <b>Mode</b> column will show <b>Transceive</b> when this node will transmit and receive to/from the connected node. It will show <b>Receive Only</b> or <b>Local Monitor</b> if this node only receives from the connected node.
</li>
</ul>
</p>
<p>
Any Voter pages will show RTCM receiver details. The bars will move in near-real-time as the signal strength varies. 
The voted receiver will turn green indicating that it is being repeated.
The numbers are the relative signal strength indicator, RSSI. The value ranges from 0 to 255, a range of approximately 30db.
A value of zero means that no signal is being selected. 
The color of the bars indicate the type of RTCM client as shown on the key below the voter display.
</p>
</ul>

Some changes to note. Please see the manual for complete install and update information.

<ul>
    <li>The primary new feature is the addition of dropdown menus. The menu could get out of control
        due to managing more and more clients. Dropdowns organize your menu items (usually nodes and RTCMs) by system.
        For example you might have a Los Angles, a Las Vegas, a San Francisco and a New York system.
        Or you could put your nodes in one system, your RTCMs in another system and your hubs in yet another.</li>
    <li>Dropdowns are organized by the system= directive within allmon.ini. Any items with no system= directive
        will be shown on the navbar, as in v2. </li>
    <li>The INI file format has changed slightly:
        <ul>
            <li>Added the system= directive as mentioned above.</li>
            <li>RTCM's are now placed in the allmon INI with the rtcmnode= directive.</li>
            <li>The INI [break] stanza is non-operational and is ignored.</li>
            <li>Updated allmon.ini.example to refelect these changes.</li>
        </ul>
    </li>
    <li>The voter INI file (voter.ini.php) is no longer used and will be ignored if it exists.</li>
    <li>The login/logout link has been moved above the navbar and at the lower right corner of the header</li>
    <li>A click on the page title will fetch the Allmon index page.</li>
    <li>The about page text has been moved to the index page.</li>
    <li>For the latest changes see the Supermon2 manual.</li>
</ul>
<br>
<?php include "footer.inc"; ?>
