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
	"io/ioutil"
	"net/http"
	"os"
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

	var fps []FileProcessor
	fps = append(fps, FileProcessor{Filenamematch: "*.xlsx", Excludesheetname: []string{"目次"}})
	cfg.Files = fps

	configDir, _ := os.Getwd()
	configFilename := filepath.Join(configDir, "config.json")

	if _, err := os.Stat(configFilename); err == nil {
		fmt.Println("すでに同名でファイルが存在します。上書きしますか？(Y)", configFilename)
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
		$ ./bin/solr create_core -c kassiscore -d _default
		$ ./bin/solr config -c kassiscore -p 8983 -action set-user-property -property update.autoCreateFields -value false
		$ curl -X POST -H 'Content-type:application/json' --data-binary @tools/kassis-solr-schema.json  http://localhost:8983/solr/kassiscore/schema
	*/

	fmt.Printf("New connection\n")
	ctx := context.Background()
	// Initialize a new solr Core Admin API
	ca, err := solr.NewCoreAdmin(ctx, cfg.Solr.Serveruri, http.DefaultClient)
	if err != nil {
		log.Err(err)
		return
	}

	fmt.Printf("create core\n")
	//res, err := ca.Create(ctx, cfg.Solr.Corename, &solr.CoreCreateOpts{Config: "conf/solrconfig.xml"})
	_, err = ca.Create(ctx, cfg.Solr.Corename, &solr.CoreCreateOpts{})
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

	fmt.Printf("read schema file ok")
	fmt.Println(string(bytes))

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

	// step1
	fmt.Println("Check config path (config.json)")
	filename, err := getConfigPath()
	if err != nil {
		fmt.Printf("ng: config file ng\n")
		return "", err
	}

	fmt.Printf("ok: config file (Path:%s)\n", filename)

	cfg, _ := loadConfig(filename)
	fmt.Printf("ok: config file load and parse\n")

	// step2 : check ext folder
	extdirname, _ := os.Getwd()
	extdirname = filepath.Join(extdirname, cfg.ExtDir)

	f, err := os.Stat(extdirname)
	if err == nil && f.IsDir() {
		fmt.Printf("ok: ext dirname %s\n", extdirname)
	} else {
		fmt.Printf("ng: ext dirname %s\n", extdirname)
	}

	// step3 : check solr
	ctx := context.Background()
	conn, err := solr.NewConnection(cfg.Solr.Serveruri, cfg.Solr.Corename, http.DefaultClient)
	if err != nil {
		fmt.Printf("ng: Solr connection error (1) Path:%s %s\n", cfg.Solr.Serveruri, cfg.Solr.Corename)
	} else {
		slr, err := solr.NewSingleClient(conn)
		if err != nil {
			fmt.Printf("ng: Solr connection error (2) create client Path:%s %s\n", cfg.Solr.Serveruri, cfg.Solr.Corename)
		} else {
			err = slr.Ping(ctx)
			if err != nil {
				fmt.Printf("ng: Solr ping:%s %s\n", cfg.Solr.Serveruri, cfg.Solr.Corename)
			} else {
				fmt.Printf("ok: Solr ping:%s %s\n", cfg.Solr.Serveruri, cfg.Solr.Corename)

				//TODO: core and schema check
			}
		}
	}

	// step4 : check tika
	client := tika.NewClient(nil, cfg.Tika.Serveruri)
	if client != nil {
		fmt.Printf("ng: Tika client %s\n", cfg.Tika.Serveruri)
	} else {
		fmt.Printf("ok: Tika client %s\n", cfg.Tika.Serveruri)
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
