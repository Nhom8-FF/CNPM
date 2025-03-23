<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  <title>Quản lý khoá học qua Google Drive</title>
</head>
<body>
  <button onclick="handleAuthClick()">Đăng nhập Google</button>
  <button onclick="listFolders()">Liệt kê Folder</button>

  <div id="folderList"></div>
  <div id="fileList"></div>

  <!-- Nạp thư viện Google API -->
  <script src="https://apis.google.com/js/api.js"></script>
  <script>
    function initClient() {
      gapi.client.init({
        apiKey: 'AIzaSyAwZSsjPOntfKOzJePG_KXFMYzuVNaitQM',
        clientId: '338294210156-qog5knqsaab446bi62jkmulr76mnh8o5.apps.googleusercontent.com',
        discoveryDocs: ["https://www.googleapis.com/discovery/v1/apis/drive/v3/rest"],
        scope: 'https://www.googleapis.com/auth/drive.readonly'
      }).then(function () {
        // Đã sẵn sàng
      }, function(error) {
        console.log(JSON.stringify(error, null, 2));
      });
    }

    function handleAuthClick() {
      gapi.auth2.getAuthInstance().signIn();
    }

    function listFolders() {
      gapi.client.drive.files.list({
        'q': "mimeType = 'application/vnd.google-apps.folder' and trashed=false",
        'fields': "files(id, name)"
      }).then(function(response) {
        const folders = response.result.files;
        const folderListDiv = document.getElementById('folderList');
        folderListDiv.innerHTML = '';

        folders.forEach(folder => {
          const btn = document.createElement('button');
          btn.textContent = folder.name;
          btn.onclick = () => listFilesInFolder(folder.id);
          folderListDiv.appendChild(btn);
        });
      });
    }

    function listFilesInFolder(folderId) {
      gapi.client.drive.files.list({
        'q': `'${folderId}' in parents and trashed=false`,
        'fields': "files(id, name, mimeType, webViewLink, webContentLink)"
      }).then(function(response) {
        const files = response.result.files;
        const fileListDiv = document.getElementById('fileList');
        fileListDiv.innerHTML = '';

        files.forEach(file => {
          const div = document.createElement('div');
          div.textContent = `${file.name} (${file.mimeType})`;
          // Hoặc tạo link
          const link = document.createElement('a');
          link.href = file.webViewLink;  // Hoặc webContentLink
          link.target = '_blank';
          link.textContent = ' Mở';

          div.appendChild(link);
          fileListDiv.appendChild(div);
        });
      });
    }

    gapi.load('client:auth2', initClient);
  </script>
</body>
</html>
