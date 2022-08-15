package kassiscore

import (
	"archive/zip"
	"bufio"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"github.com/google/go-tika/tika"
	"github.com/rs/zerolog/log"
	"github.com/vanng822/go-solr/solr"
	"io"
	"io/ioutil"
	"net/http"
	"net/url"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"strconv"
	"time"
)

var ConfigFileName = "config.json"
var DisplayModeOnCheckFunction = "error-only"

const (
	MSGINFO  = false
	MSGERROR = true
)

//var SolrFolders = []string{"solr-8.11.2", "solr-8.11.1", "solr-8.11", "solr-8", "solr"}

type FileProcessor struct {
	Filenamematch    string   `json:"filenamematch"`
	Excludesheetname []string `json:"excludesheetname"`
}

type KENVCONF struct {
	HomeDir   string `json:"homeDir"`
	ExtDir    string `json:"extDir"`
	ExtAppDir string `json:"extAppDir"`
	WebServer struct {
		Listen string `json:"listen"`
	} `json:"web"`
	Ndl struct {
		APIENDPOINT_SRU    string `json:"apiendpoint_sru"`
		APIENDPOINT_OAIPMH string `json:"apiendpoint_oaipmh"`
	} `json:"ndl"`
	Solr struct {
		Home      string `json:"home"`
		Serveruri string `json:"serveruri"`
		Corename  string `json:"corename"`
	} `json:"solr"`
	Tika struct {
		Home      string `json:"home"`
		Serveruri string `json:"serveruri"`
	} `json:"tika"`
	Files []FileProcessor `json:"files"`
}

const NGLBL = "\u001B[31mNG\u001B[0m"
const OKLBL = "\u001B[32mOK\u001B[0m"

const TIKAAPPURL = "https://dlcdn.apache.org/tika/2.4.1/tika-server-standard-2.4.1.jar"
const SOLRAPPURL = "https://www.apache.org/dyn/closer.lua/lucene/solr/8.11.2/solr-8.11.2.zip?action=download"

var (
	KCONF *KENVCONF
)

func DownloadApps() {
	log.Info().Msg("download tika-server")

	var storepath string

	rootdir, _ := os.Getwd()

	storepath = filepath.Join(rootdir, "tools", "app")
	if f, err := os.Stat(storepath); os.IsNotExist(err) || !f.IsDir() {
		fmt.Printf("create directory %s\n", storepath)
		err := os.Mkdir(storepath, 0755)
		if err != nil {
			fmt.Println(err)
			return
		}
	}

	u, err := url.Parse(TIKAAPPURL)
	if err != nil {
		log.Fatal().Err(err)
		return
	}
	storepath = filepath.Join(rootdir, "tools", "app", filepath.Base(u.Path))
	if err := DownloadFile(storepath, TIKAAPPURL); err != nil {
		panic(err)
	}

	log.Info().Msg("download apache solr")

	u, err = url.Parse(SOLRAPPURL)
	if err != nil {
		log.Fatal().Err(err)
		return
	}
	storepath = filepath.Join(rootdir, "tools", "app", filepath.Base(u.Path))
	if err := DownloadFile(storepath, SOLRAPPURL); err != nil {
		panic(err)
	}

	//s := strings.Replace(filepath.Base(u.Path), filepath.Ext(u.Path), "", 1)
	outpath := filepath.Join(rootdir, "tools", "app")

	log.Info().Msgf("unzip solr: %s", outpath)

	err = Unzip(storepath, outpath)
}

func DownloadFile(filepath string, url string) error {
	resp, err := http.Get(url)
	if err != nil {
		return err
	}
	defer func(Body io.ReadCloser) {
		err := Body.Close()
		if err != nil {

		}
	}(resp.Body)

	out, err := os.Create(filepath)
	if err != nil {
		return err
	}
	defer func(out *os.File) {
		err := out.Close()
		if err != nil {
			fmt.Println(err)
		}
	}(out)

	_, err = io.Copy(out, resp.Body)
	return err
}

func GenerateDefaultConfigSet() {

	cfg := new(KENVCONF)
	cfg.Solr.Serveruri = "http://localhost:8983"
	cfg.Solr.Corename = "kassiscore"
	cfg.Tika.Serveruri = "http://localhost:9998"
	cfg.WebServer.Listen = ":1323"
	cfg.ExtDir = "ext"

	homedir, _ := os.Getwd()
	toolshome, _ := os.Getwd()
	toolshome = filepath.Join(toolshome, "tools")
	apphome := filepath.Join(toolshome, "app")

	cfg.HomeDir = homedir
	cfg.ExtAppDir = apphome

	solrhome := filepath.Join(apphome, "solr-8.11.2")
	if f, err := os.Stat(solrhome); os.IsNotExist(err) || !f.IsDir() {
		fmt.Println("Solr home:ng", solrhome)
	}

	cfg.Solr.Home = solrhome

	cfg.Tika.Home = apphome

	cfg.Ndl.APIENDPOINT_SRU = "https://iss.ndl.go.jp/api/sru"
	cfg.Ndl.APIENDPOINT_OAIPMH = "https://iss.ndl.go.jp/api/oaipmh"

	var fps []FileProcessor
	fps = append(fps, FileProcessor{Filenamematch: "*.xlsx", Excludesheetname: []string{"目次"}})
	cfg.Files = fps

	configDir, _ := os.Getwd()
	configFilename := filepath.Join(configDir, "config.json")

	if _, err := os.Stat(configFilename); err == nil {
		fmt.Println("overwrite?(Y/N)", configFilename)
		scanner := bufio.NewScanner(os.Stdin)
		for scanner.Scan() {
			if scanner.Text() == "Y" {
				break
			}
		}
	}

	f, err := os.Create(configFilename)
	if err != nil {
		log.Err(err)
		return
	}
	defer func(f *os.File) {
		err := f.Close()
		if err != nil {
			fmt.Println(err)
		}
	}(f)

	enc := json.NewEncoder(f)
	enc.SetIndent("", "    ")
	err = enc.Encode(cfg)
	if err != nil {
		log.Err(err)
		return
	}
	fmt.Printf("generate default configset path:%s\n", configFilename)

}

func StartSolr(cfg *KENVCONF) {
	fmt.Println("start solr.")

	// step3-b : check java home
	javahome := os.Getenv("JAVA_HOME")
	if javahome == "" {
		fmt.Println("error: no set JAVA_HOME")
		return
	}

	solrbin := filepath.Join(cfg.Solr.Home, "bin")
	if f, err := os.Stat(solrbin); os.IsNotExist(err) || !f.IsDir() {
		fmt.Println("Solr bin:ng", solrbin)
	} else {
		fmt.Println("Solr bin:ok", solrbin)
	}

	solrcmd := filepath.Join(cfg.Solr.Home, "bin", "solr")
	cmd := exec.Command(solrcmd, "start")
	err := cmd.Start()
	if err != nil {
		fmt.Println("error", err.Error())
		return
	}
	waittimesec := 10

	fmt.Printf("wait time %dsec\n", waittimesec)
	time.Sleep(time.Second * time.Duration(waittimesec))
	fmt.Println("ProcessID:", cmd.Process.Pid)

	vs, err := SolrServerPing(cfg.Solr.Serveruri)
	if err != nil {
		fmt.Printf("error: %s\n", err.Error())
	} else {
		fmt.Printf("solr-spec-version:%s\n", vs)
	}
}

func StopSolr(cfg *KENVCONF) {
	fmt.Println("stop solr.")

	solrbin := filepath.Join(cfg.Solr.Home, "bin")
	if f, err := os.Stat(solrbin); os.IsNotExist(err) || !f.IsDir() {
		fmt.Println("Solr bin:ng", solrbin)
	} else {
		fmt.Println("Solr bin:ok", solrbin)
	}

	//TODO: ポート指定をする
	solrcmd := filepath.Join(cfg.Solr.Home, "bin", "solr")
	cmd, err := exec.Command(solrcmd, "stop", "-all", "-V").Output()
	//err := cmd.Start()
	if err != nil {
		fmt.Println("error", err.Error())
		return
	}
	fmt.Println(string(cmd))
}

func StartTika(cfg *KENVCONF) error {
	fmt.Println("start tika.")

	javahome := os.Getenv("JAVA_HOME")
	javaexe := filepath.Join(javahome, "bin", "java.exe")
	tikajar := filepath.Join(cfg.Tika.Home, "tika-server-standard-2.4.1.jar")
	tikalogpath := filepath.Join(cfg.ExtAppDir, "tika.log")

	c := []string{javaexe, "-jar", tikajar, "2>&1", tikalogpath}
	cmd := exec.Command(c[0], c[1:]...)
	err := cmd.Start()
	if err != nil {
		fmt.Println("error", err.Error())
		return err
	}
	waittimesec := 5

	fmt.Printf("wait time %dsec\n", waittimesec)
	time.Sleep(time.Second * time.Duration(waittimesec))
	fmt.Println("ProcessID:", cmd.Process.Pid)

	pidfile := filepath.Join(cfg.Tika.Home, "tika.pid")
	fmt.Println(pidfile)
	file, err := os.Create(pidfile)
	if err != nil {
		fmt.Println(err)
		return err
	}
	_, err = file.WriteString(strconv.Itoa(cmd.Process.Pid))
	if err != nil {
		return err
	}
	err = file.Close()
	if err != nil {
		return err
	}

	vs, err := TikaPing(cfg.Tika.Serveruri)
	if err != nil {
		fmt.Println(err)
		return err
	} else {
		fmt.Printf("Tika version is:%s\n", vs)
	}
	return nil
}

func StopTika(cfg *KENVCONF) error {
	fmt.Println("stop tika.")

	pidfile := filepath.Join(cfg.Tika.Home, "tika.pid")
	fmt.Println(pidfile)

	file, err := os.Open(pidfile)
	if err != nil {
		return err
	}

	pids := ""

	scanner := bufio.NewScanner(file)
	for scanner.Scan() {
		pids = scanner.Text()
		//fmt.Println(pids)
		break
	}
	err = file.Close()
	if err != nil {
		return err
	}

	if err := scanner.Err(); err != nil {
		return err
	}

	if pids == "" {
		return fmt.Errorf("pid file is empty")
	}

	pid, err := strconv.Atoi(pids)
	if err != nil {
		return err
	}
	process, err := os.FindProcess(pid)
	if err != nil {
		fmt.Println(err)
		return err
	}
	err = process.Kill()
	if err != nil {
		fmt.Println(err)
		return err
	}

	err = os.Remove(pidfile)
	if err != nil {
		return err
	}

	return nil
}

func SetupSolr(corename string) error {
	fmt.Println("start setup solr.")
	fmt.Printf("corenname:%s\n", corename)

	filename, err := getConfigPath()
	if err != nil {
		fmt.Printf("ng: config file ng\n")
		return err
	}
	cfg, _ := loadConfig(filename)

	if corename == "" {
		corename = cfg.Solr.Corename
	}
	fmt.Printf("SolrServerPath:%s corename:%s\n", cfg.Solr.Serveruri, corename)
	/*
		$ ./bin/solr delete -c kassiscore
		$ cp -r server/solr/configsets/_default server/solr/core1
		$ ./bin/solr create_core -c kassiscore -d _default
		$ ./bin/solr config -c kassiscore -p 8983 -action set-user-property -property update.autoCreateFields -value false
		$ curl -X POST -H 'Content-type:application/json' --data-binary @tools/kassis-solr-schema.json  http://localhost:8983/solr/kassiscore/schema
	*/

	fmt.Printf("connect to solr\n")
	// Initialize a new solr Core Admin API
	ca, err := solr.NewCoreAdmin(cfg.Solr.Serveruri + "/solr/")
	if err != nil {
		log.Err(err)
		return err
	}

	//TODO: core exist?

	// copy configset
	// cp -r server/solr/configsets/_default server/solr/<corename>
	fmt.Printf("copy solr configset\n")
	defaultsolrconfigset := filepath.Join(cfg.Solr.Home, "server", "solr", "configsets", "_default")
	destdir := filepath.Join(cfg.Solr.Home, "server", "solr", corename)

	log.Debug().Msgf("%s to %s", defaultsolrconfigset, destdir)

	err = CopyDirectory(defaultsolrconfigset, destdir)
	if err != nil {
		fmt.Printf("%s", err)
		log.Err(err)
		return err
	}

	// create core
	fmt.Printf("create core\n")
	params := &url.Values{}
	params.Add("name", corename)
	//params.Add("config", "solrconfig.xml")
	//params.Add("dataDir", "data")
	_, err = ca.Action("CREATE", params)
	if err != nil {
		log.Error().Msg("fail create core")
		log.Err(err).Msg("error reason:")
		return err
	}

	// update schema
	fmt.Printf("read schema file\n")

	schemafilename, _ := os.Getwd()
	schemafilename = filepath.Join(schemafilename, "tools", "kassis-solr-schema.json")

	err = SolrSchemaUpdate(cfg.Solr.Serveruri, corename, schemafilename)
	if err != nil {
		log.Error().Msg("fail update schema")
		log.Err(err).Msg("error reason:")
		return err
	}

	// reload core
	fmt.Println("reload core")

	params = &url.Values{}
	params.Add("core", corename)
	_, err = ca.Action("RELOAD", params)
	if err != nil {
		log.Err(err)
		return err
	}

	fmt.Println("success")

	waittimesec := 5
	fmt.Printf("wait time %dsec\n", waittimesec)
	time.Sleep(time.Second * time.Duration(waittimesec))

	fmt.Println("complete")

	return nil
}

func dism(msg string, isError bool) {
	if isError || DisplayModeOnCheckFunction == "full" {
		fmt.Print(msg)
	}
}

// CheckConfigAndConnections は、設定が正しいかをチェックする関数です。
// display mode : full or error-only
func CheckConfigAndConnections(displaymode string) error {

	var last_error error
	DisplayModeOnCheckFunction = displaymode

	fmt.Printf("check start: %s\n", DisplayModeOnCheckFunction)

	// step1:config
	filename, err := getConfigPath()
	if err != nil {
		dism("read config file:"+NGLBL+"\n", MSGERROR)
		return err
	}

	dism("read config file:"+OKLBL+"\n", MSGINFO)

	cfg, _ := loadConfig(filename)
	dism(fmt.Sprintf("config file load and parse:"+OKLBL+"\n"), MSGINFO)

	// step2 : check ext folder
	extdirname, _ := os.Getwd()
	extdirname = filepath.Join(extdirname, cfg.ExtDir)

	f, err := os.Stat(extdirname)
	if err == nil && f.IsDir() {
		last_error = err
		dism(fmt.Sprintf("ext dirname:"+OKLBL+" (%s)\n", extdirname), MSGINFO)
	} else {
		dism(fmt.Sprintf("ext dirname:"+NGLBL+" (%s)\n", extdirname), MSGERROR)
	}

	// step3 : check java
	out, err := exec.Command("java", "--version").Output()
	if err != nil {
		last_error = err
		dism("Java command:"+NGLBL+"\n", MSGERROR)
		dism(err.Error(), MSGERROR)
	} else {
		dism("Java command:"+OKLBL+"\n", MSGINFO)
		dism("---\n", MSGINFO)
		dism(string(out)+"\n", MSGINFO)
		dism("---\n", MSGINFO)
	}

	// step3-b : check java home
	javahome := os.Getenv("JAVA_HOME")
	if javahome == "" {
		last_error = fmt.Errorf("no set JAVA_HOME")
		dism("Env JAVA_HOME:"+NGLBL+"\n", MSGERROR)
	} else {
		javaexe := filepath.Join(javahome, "bin", "java.exe")
		if runtime.GOOS != "windows" {
			javaexe = filepath.Join(javahome, "bin", "java")
		}
		_, err := os.Stat(javaexe)
		if err != nil {
			last_error = fmt.Errorf("no find java.exe")
			dism(fmt.Sprintf("ERROR: java.exe "+NGLBL+" not found in %s : Please set JAVA_HOME to a valid JRE / JDK directory.\n", javaexe), MSGERROR)
		}
	}

	// step4 : check solr
	solrhome := filepath.Join(cfg.Solr.Home)
	if f, err := os.Stat(solrhome); os.IsNotExist(err) || !f.IsDir() {
		last_error = fmt.Errorf("no exists solr home: %s", solrhome)
		dism(fmt.Sprintf("Solr home:"+NGLBL+" %s\n", solrhome), MSGERROR)
	} else {
		dism(fmt.Sprintf("Solr home:"+OKLBL+" %s\n", solrhome), MSGINFO)
	}

	solrbin := filepath.Join(cfg.Solr.Home, "bin")
	if f, err := os.Stat(solrbin); os.IsNotExist(err) || !f.IsDir() {
		last_error = fmt.Errorf("no exists solr bin: %s", solrbin)
		dism(fmt.Sprintf("Solr bin:"+NGLBL+" %s\n", solrbin), MSGERROR)
	} else {
		dism(fmt.Sprintf("Solr bin:"+OKLBL+" %s\n", solrbin), MSGINFO)
	}

	defaultsolrconfigset := filepath.Join(cfg.Solr.Home, "server", "solr", "configsets", "_default")
	if f, err := os.Stat(defaultsolrconfigset); os.IsNotExist(err) || !f.IsDir() {
		last_error = fmt.Errorf("no default config set: %s", defaultsolrconfigset)
		dism(fmt.Sprintf("Solr default config set:"+NGLBL+" %s\n", defaultsolrconfigset), MSGERROR)
	} else {
		dism(fmt.Sprintf("Solr default config set:"+OKLBL+" %s\n", defaultsolrconfigset), MSGINFO)
	}

	// step5 : check solr
	uri := cfg.Solr.Serveruri + "/solr"
	si, err := solr.NewSolrInterface(uri, cfg.Solr.Corename)
	if err != nil {
		last_error = fmt.Errorf("no solr coneection")
		dism(fmt.Sprintf("Solr connection:"+NGLBL+" error (1) Path:%s %s\n", cfg.Solr.Serveruri, cfg.Solr.Corename), MSGERROR)
	} else {
		//admin core
		vs, err := SolrServerPing(cfg.Solr.Serveruri)
		if err != nil {
			last_error = fmt.Errorf("no solr admincore")
			dism(fmt.Sprintf("Solr admincore:"+NGLBL+" error: %s\n", err.Error()), MSGERROR)
		} else {
			dism(fmt.Sprintf("Solr admincore:"+OKLBL+" solr-spec-version:%s\n", vs), MSGINFO)

			//core check
			_, qtime, err := si.Ping()
			if err != nil {
				last_error = fmt.Errorf("solr ping error")
				dism(fmt.Sprintf("Solr core ping:"+NGLBL+" (%s %s)\n", cfg.Solr.Serveruri, cfg.Solr.Corename), MSGERROR)
			} else {
				dism(fmt.Sprintf("Solr core ping:"+OKLBL+" (%s %s) qtime:%d\n", cfg.Solr.Serveruri, cfg.Solr.Corename, qtime), MSGINFO)

				//TODO: core and schema check
			}
		}
	}

	// step6 : check tika
	vs, err := TikaPing(cfg.Tika.Serveruri)
	if err != nil {
		last_error = fmt.Errorf("tika ping error")
		dism(fmt.Sprintf("Tika ping:"+NGLBL+" (%s) %s\n", cfg.Tika.Serveruri, err), MSGERROR)
	} else {
		dism(fmt.Sprintf("Tika ping:"+OKLBL+" (%s) %s\n", cfg.Tika.Serveruri, vs), MSGINFO)
	}

	return last_error
}

func TikaPing(tikauri string) (string, error) {

	var err error
	vs := ""

	client := tika.NewClient(nil, tikauri)
	if client != nil {
		vs, err = client.Version(context.Background())
		if err != nil {
			return "", err
		}
	} else {
		return "", fmt.Errorf("unable to create an instance of the new tika client")
	}

	return vs, nil
}

func LoadConfig() *KENVCONF {
	fname, err := getConfigPath()
	if err != nil {
		log.Err(nil).Msg("no fname")
	}

	log.Info().Str("fname", fname)

	cfg, _ := loadConfig(fname)
	return cfg
}

// 設定ファイルのパスを決定する
// コマンドラインの引数で指定されている場合はこの関数前で決定する
func getConfigPath() (string, error) {
	var configDir string
	var configFilename string

	// 1. 環境変数(KASSISCONFIG)
	home := os.Getenv("KASSISCONFIG")

	log.Info().Msg(home)

	if home != "" {
		configFilename = os.Getenv("KASSISCONFIG")
		_, err := os.Stat(configFilename)
		if err == nil {
			return configFilename, nil
		}
	}

	// 2. ./config.json
	configDir, _ = os.Getwd()
	configFilename = filepath.Join(configDir, ConfigFileName)

	log.Info().Msg(configFilename)

	_, err := os.Stat(configFilename)
	if err == nil {
		return configFilename, nil
	}

	// 3. ./config/config.json
	configDir, _ = os.Getwd()
	configFilename = filepath.Join(configDir, "config", ConfigFileName)
	_, err = os.Stat(configFilename)
	if err == nil {
		return configFilename, nil
	}

	// 4. ~/.config/kassis/config.json (Windows以外）
	//    APPDATA/kassis/config.json
	home = os.Getenv("HOME")
	if home == "" && runtime.GOOS == "windows" {
		configDir = os.Getenv("APPDATA")
		configDir = filepath.Join(configDir, "kassis", ConfigFileName)
	} else {
		configDir = filepath.Join(home, ".config", "kassis", ConfigFileName)
	}
	_, err = os.Stat(configFilename)
	if err == nil {
		return configFilename, nil
	}

	return "", errors.New(fmt.Sprintf("Unable to open config file"))
}

func loadConfig(fname string) (*KENVCONF, error) {
	f, err := os.Open(fname)
	if err != nil {
		log.Fatal().Err(err).Msg("can not load config file")
		return nil, err
	}
	defer func(f *os.File) {
		err := f.Close()
		if err != nil {
			fmt.Println(err)
		}
	}(f)

	var cfg KENVCONF
	err = json.NewDecoder(f).Decode(&cfg)
	return &cfg, err
}

func CopyDirectory(scrDir, dest string) error {
	entries, err := ioutil.ReadDir(scrDir)
	if err != nil {
		return err
	}
	for _, entry := range entries {
		sourcePath := filepath.Join(scrDir, entry.Name())
		destPath := filepath.Join(dest, entry.Name())

		fileInfo, err := os.Stat(sourcePath)
		if err != nil {
			return err
		}

		// for linux
		/*
			stat, ok := fileInfo.Sys().(*syscall.Stat_t)
			if !ok {
				return fmt.Errorf("failed to get raw syscall.Stat_t data for '%s'", sourcePath)
			}
		*/
		switch fileInfo.Mode() & os.ModeType {
		case os.ModeDir:
			if err := CreateIfNotExists(destPath, 0755); err != nil {
				return err
			}
			if err := CopyDirectory(sourcePath, destPath); err != nil {
				return err
			}
		case os.ModeSymlink:
			if err := CopySymLink(sourcePath, destPath); err != nil {
				return err
			}
		default:

			if err := Copy(sourcePath, destPath); err != nil {
				return err
			}
		}
		// for unix
		/*
			if err := os.Lchown(destPath, int(stat.Uid), int(stat.Gid)); err != nil {
				return err
			}
			isSymlink := entry.Mode()&os.ModeSymlink != 0
			if !isSymlink {
				if err := os.Chmod(destPath, entry.Mode()); err != nil {
					return err
				}
			}
		*/
	}
	return nil
}

func Copy(srcFile, dstFile string) error {
	out, err := os.Create(dstFile)
	if err != nil {
		return err
	}

	defer func(out *os.File) {
		err := out.Close()
		if err != nil {
			fmt.Println(err)
		}
	}(out)

	in, err := os.Open(srcFile)
	defer func(in *os.File) {
		err := in.Close()
		if err != nil {
			fmt.Println(err)
		}
	}(in)
	if err != nil {
		return err
	}

	_, err = io.Copy(out, in)
	if err != nil {
		return err
	}

	return nil
}

func Exists(filePath string) bool {
	if _, err := os.Stat(filePath); os.IsNotExist(err) {
		return false
	}

	return true
}

func CreateIfNotExists(dir string, perm os.FileMode) error {
	if Exists(dir) {
		return nil
	}

	if err := os.MkdirAll(dir, perm); err != nil {
		return fmt.Errorf("failed to create directory: '%s', error: '%s'", dir, err.Error())
	}

	return nil
}

func CopySymLink(source, dest string) error {
	link, err := os.Readlink(source)
	if err != nil {
		return err
	}
	return os.Symlink(link, dest)
}

func Unzip(src, dest string) error {
	r, err := zip.OpenReader(src)
	if err != nil {
		return err
	}
	defer r.Close()

	for _, f := range r.File {
		rc, err := f.Open()
		if err != nil {
			fmt.Println(err)
			return err
		}

		path := filepath.Join(dest, f.Name)
		if f.FileInfo().IsDir() {
			os.MkdirAll(path, f.Mode())
		} else {
			f, err := os.OpenFile(
				path, os.O_WRONLY|os.O_CREATE|os.O_TRUNC, f.Mode())
			if err != nil {
				fmt.Println(err)
				rc.Close()
				f.Close()
				return err
			}

			_, err = io.Copy(f, rc)
			if err != nil {
				fmt.Println(err)
				rc.Close()
				f.Close()
				return err
			}
			f.Close()
		}
		rc.Close()
	}

	return nil
}
