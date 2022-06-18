package kassiscore

import (
	"bufio"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"github.com/google/go-tika/tika"
	"github.com/mecenat/solr"
	"github.com/rs/zerolog/log"
	"io"
	"io/ioutil"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"strings"
)

var ConfigFileName = "config.json"

type FileProcessor struct {
	Filenamematch    string   `json:"filenamematch"`
	Excludesheetname []string `json:"excludesheetname"`
}

type KENVCONF struct {
	ExtDir    string `json:"extDir"`
	WebServer struct {
		Listen string `json:"listen"`
	} `json:"web"`
	Solr struct {
		Home      string `json:"home"`
		Serveruri string `json:"serveruri"`
		Corename  string `json:"corename"`
	} `json:"solr"`
	Tika struct {
		Serveruri string `json:"serveruri"`
	} `json:"tika"`
	Files []FileProcessor `json:"files"`
}

//Child json.RawMessage

func GenerateDefaultConfigSet() {

	cfg := new(KENVCONF)
	cfg.Solr.Serveruri = "http://localhost:8983"
	cfg.Solr.Corename = "kassiscore"
	cfg.Tika.Serveruri = "http://localhost:9998"
	cfg.WebServer.Listen = ":1323"
	cfg.ExtDir = "ext"

	solrhome, _ := os.Getwd()
	solrhome = filepath.Join(solrhome, "tools", "app", "solr-8.11.1")
	cfg.Solr.Home = solrhome

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
	defer f.Close()

	enc := json.NewEncoder(f)
	enc.SetIndent("", "    ")
	err = enc.Encode(cfg)
	if err != nil {
		log.Err(err)
		return
	}
	fmt.Printf("generate default configset path:%s", configFilename)

}

func SetupSolr() {
	fmt.Println("start setup solr.")

	filename, err := getConfigPath()
	if err != nil {
		fmt.Printf("ng: config file ng\n")
		return
	}
	cfg, _ := loadConfig(filename)

	fmt.Printf("SolrServerPath:%s corename:%s\n", cfg.Solr.Serveruri, cfg.Solr.Corename)
	/*
			$ ./bin/solr delete -c kassiscore
		 cp -r server/solr/configsets/_default server/solr/core1
			$ ./bin/solr create_core -c kassiscore -d _default
			$ ./bin/solr config -c kassiscore -p 8983 -action set-user-property -property update.autoCreateFields -value false
			$ curl -X POST -H 'Content-type:application/json' --data-binary @tools/kassis-solr-schema.json  http://localhost:8983/solr/kassiscore/schema
	*/

	fmt.Printf("connect to solr\n")
	ctx := context.Background()
	// Initialize a new solr Core Admin API
	ca, err := solr.NewCoreAdmin(ctx, cfg.Solr.Serveruri, http.DefaultClient)
	if err != nil {
		log.Err(err)
		return
	}

	//TODO: core exist?

	// cp -r server/solr/configsets/_default server/solr/<corename>
	fmt.Printf("copy solr configset\n")
	defaultsolrconfigset := filepath.Join(cfg.Solr.Home, "server", "solr", "configsets", "_default")
	destdir := filepath.Join(cfg.Solr.Home, "server", "solr", cfg.Solr.Corename)
	err = CopyDirectory(defaultsolrconfigset, destdir)
	if err != nil {
		fmt.Printf("%s", err)
		log.Err(err)
		return
	}

	fmt.Printf("create core\n")
	//res, err := ca.Create(ctx, cfg.Solr.Corename, &solr.CoreCreateOpts{Config: "conf/solrconfig.xml"})
	_, err = ca.Create(ctx, cfg.Solr.Corename, &solr.CoreCreateOpts{InstanceDir: cfg.Solr.Corename})
	if err != nil {
		//TODO: delete core
		fmt.Printf("%s", err)
		log.Err(err)
		return
	}

	fmt.Printf("read schema file\n")

	schemafilename, _ := os.Getwd()
	schemafilename = filepath.Join(schemafilename, "tools", "kassis-solr-schema.json")

	bytes, err := ioutil.ReadFile(schemafilename)
	if err != nil {
		log.Err(err)
		return
	}

	//fmt.Println(string(bytes))
	fmt.Println("Post schema file")
	url := cfg.Solr.Serveruri + "/solr/" + cfg.Solr.Corename + "/schema"
	req, err := http.NewRequest(
		"POST",
		url,
		strings.NewReader(string(bytes)),
	)
	if err != nil {
		log.Err(err)
		return
	}

	log.Debug().Msgf("%s", req.Header)

	// Content-Type 設定
	req.Header.Set("Content-Type", "Content-type:application/json")

	client := &http.Client{}
	resp, err := client.Do(req)
	if err != nil {
		log.Err(err)
		return
	}
	defer resp.Body.Close()

}

func CheckConfigAndConnections() (string, error) {

	fmt.Println("check start")

	// step1:config
	filename, err := getConfigPath()
	if err != nil {
		fmt.Printf("read config file:ng\n")
		return "", err
	}

	fmt.Printf("read config file:ok\n")

	cfg, _ := loadConfig(filename)
	fmt.Printf("config file load and parse:ok\n")

	// step2 : check ext folder
	extdirname, _ := os.Getwd()
	extdirname = filepath.Join(extdirname, cfg.ExtDir)

	f, err := os.Stat(extdirname)
	if err == nil && f.IsDir() {
		fmt.Printf("ext dirname:ok (%s)\n", extdirname)
	} else {
		fmt.Printf("ext dirname:ng (%s)\n", extdirname)
	}

	// step3 : check java
	out, err := exec.Command("java", "--version").Output()
	if err != nil {
		fmt.Printf("Java command:ng\n", err)
	} else {
		fmt.Println("Java command:ok")
		fmt.Println("---")
		fmt.Println(string(out))
		fmt.Println("---")
	}

	// step4 : check solr
	solrhome := filepath.Join(cfg.Solr.Home)
	if f, err := os.Stat(solrhome); os.IsNotExist(err) || !f.IsDir() {
		fmt.Println("Solr home:ng", solrhome)
	} else {
		fmt.Println("Solr home:ok", solrhome)
	}

	solrbin := filepath.Join(cfg.Solr.Home, "bin")
	if f, err := os.Stat(solrbin); os.IsNotExist(err) || !f.IsDir() {
		fmt.Println("Solr bin:ng", solrbin)
	} else {
		fmt.Println("Solr bin:ok", solrbin)
	}

	defaultsolrconfigset := filepath.Join(cfg.Solr.Home, "server", "solr", "configsets", "_default")
	if f, err := os.Stat(defaultsolrconfigset); os.IsNotExist(err) || !f.IsDir() {
		fmt.Println("Solr default config set:ng ", defaultsolrconfigset)
	} else {
		fmt.Println("Solr default config set:ok ", defaultsolrconfigset)
	}

	// step5 : check solr
	ctx := context.Background()
	conn, err := solr.NewConnection(cfg.Solr.Serveruri, cfg.Solr.Corename, http.DefaultClient)
	if err != nil {
		fmt.Printf("Solr connection:ng error (1) Path:%s %s\n", cfg.Solr.Serveruri, cfg.Solr.Corename)
	} else {
		slr, err := solr.NewSingleClient(conn)
		if err != nil {
			fmt.Printf("Solr connection:ng error (2) create client Path:%s %s\n", cfg.Solr.Serveruri, cfg.Solr.Corename)
		} else {
			err = slr.Ping(ctx)
			if err != nil {
				fmt.Printf("Solr core ping:ng (%s %s)\n", cfg.Solr.Serveruri, cfg.Solr.Corename)
			} else {
				fmt.Printf("Solr core ping:ok (%s %s)\n", cfg.Solr.Serveruri, cfg.Solr.Corename)

				//TODO: core and schema check
			}
		}
	}

	// step6 : check tika
	client := tika.NewClient(nil, cfg.Tika.Serveruri)
	if client != nil {
		vs, err := client.Version(context.Background())
		if err != nil {
			fmt.Printf("Tika client:ng %s %s\n", cfg.Tika.Serveruri, err)
		} else {
			fmt.Printf("Tika client:ok %s %s\n", cfg.Tika.Serveruri, vs)
		}
	} else {
		fmt.Printf("Tika client:ng %s\n", cfg.Tika.Serveruri)
	}
	return "", nil
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
	}
	defer f.Close()

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

	defer out.Close()

	in, err := os.Open(srcFile)
	defer in.Close()
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
